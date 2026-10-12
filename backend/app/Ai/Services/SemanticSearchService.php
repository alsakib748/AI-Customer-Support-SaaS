<?php

// app/Ai/Services/SemanticSearchService.php

namespace App\Ai\Services;

use App\Ai\Exceptions\EmbeddingException;
use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SemanticSearchService
{
    protected EmbeddingService $embeddingService;

    protected KnowledgeBaseChunkingService $chunkingService;

    protected $cacheResults = true;

    public function __construct(
        EmbeddingService $embeddingService,
        KnowledgeBaseChunkingService $chunkingService
    ) {
        $this->embeddingService = $embeddingService;
        $this->chunkingService = $chunkingService;
    }

    /**
     * Search knowledge base semantically
     */
    public function semanticSearch(string $query, int $limit = 5): array
    {
        try {
            // Embed the query into the same space the documents were embedded in.
            $queryEmbedding = $this->embeddingService->generateEmbedding($query, 'RETRIEVAL_QUERY');
        } catch (EmbeddingException $e) {
            Log::warning('Query embedding failed; falling back to keyword search', [
                'error' => $e->getMessage(),
            ]);

            return $this->keywordSearchFallback($query, $limit);
        }

        // Get all published, AI-eligible articles (tenant DB connection scopes this).
        $articles = KnowledgeBaseArticle::publishedAndEligible()->get();

        if ($articles->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($articles as $article) {
            try {
                // Get or generate article embeddings
                $articleEmbeddings = $this->getArticleEmbeddings($article);
            } catch (EmbeddingException $e) {
                // Never fabricate vectors: skip this article rather than score it.
                Log::warning('Skipping article with failed embeddings', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // Calculate similarity scores
            foreach ($articleEmbeddings as $chunk) {
                if (empty($chunk['embedding'])) {
                    continue;
                }

                $similarity = $this->embeddingService->cosineSimilarity(
                    $queryEmbedding,
                    $chunk['embedding']
                );

                if ($similarity > 0.5) { // Threshold
                    $results[] = [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'content' => $chunk['content'],
                        'similarity' => $similarity,
                        'category' => $article->category?->name,
                        'score' => $similarity,
                    ];
                }
            }
        }

        // Sort by similarity (highest first)
        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return top results
        return array_slice($results, 0, $limit);
    }

    /**
     * Get or generate article embeddings.
     *
     * The cache key is namespaced by tenant, embedding model and article
     * content version, so a stale entry can never score a changed article and
     * one tenant can never read another tenant's vectors.
     */
    protected function getArticleEmbeddings($article): array
    {
        $cacheKey = $this->embeddingService->articleCacheKey(
            $article->id,
            (string) $article->title,
            (string) $article->content,
            $article->updated_at?->timestamp
        );

        if ($this->cacheResults) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        }

        // Chunk the article
        $chunks = $this->chunkingService->chunkArticle($article);

        if ($chunks === []) {
            return [];
        }

        $vectors = $this->embeddingService->batchGenerateEmbeddings(
            array_map(fn ($chunk) => $chunk['content'], $chunks),
            'RETRIEVAL_DOCUMENT'
        );

        $embeddings = [];
        foreach ($chunks as $position => $chunk) {
            $embeddings[] = [
                'content' => $chunk['content'],
                'embedding' => $vectors[$position] ?? [],
                'index' => $chunk['index'],
                'content_hash' => sha1($chunk['content']),
            ];
        }

        if ($this->cacheResults) {
            Cache::put($cacheKey, $embeddings, (int) config('ai.embeddings.cache_ttl', 86400));
        }

        return $embeddings;
    }

    /**
     * Keyword search fallback
     */
    protected function keywordSearchFallback(string $query, int $limit = 5): array
    {
        try {
            $articles = KnowledgeBaseArticle::published()
                ->whereIn('visibility', ['ai', 'public'])
                ->where(function ($q) use ($query) {
                    $q->where('title', 'ILIKE', "%{$query}%")
                        ->orWhere('content', 'ILIKE', "%{$query}%")
                        ->orWhere('excerpt', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            $results = [];
            foreach ($articles as $article) {
                $results[] = [
                    'article_id' => $article->id,
                    'title' => $article->title,
                    'content' => $article->getExcerpt(300),
                    'similarity' => 0.7,
                    'category' => $article->category?->name,
                    'score' => 0.7,
                ];
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Keyword search fallback failed:', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Search with context (for AI agent)
     */
    public function searchWithContext(string $query, int $limit = 3): array
    {
        $results = $this->semanticSearch($query, $limit);

        // Format for AI context
        $context = [];
        foreach ($results as $result) {
            $context[] = [
                'title' => $result['title'],
                'content' => $result['content'],
                'source' => 'Knowledge Base',
                'category' => $result['category'] ?? 'General',
                'relevance' => round($result['similarity'] * 100, 1).'%',
            ];
        }

        return $context;
    }

    /**
     * Search and generate AI response
     */
    public function searchAndGenerateResponse(string $query): array
    {
        // Search for relevant content
        $context = $this->searchWithContext($query, 3);

        // If no context found, return empty
        if (empty($context)) {
            return [
                'success' => false,
                'message' => 'No relevant information found.',
                'context' => [],
            ];
        }

        // Format context for AI
        $formattedContext = "Based on the following information:\n\n";
        foreach ($context as $item) {
            $formattedContext .= '### '.$item['title']."\n";
            $formattedContext .= $item['content']."\n\n";
        }

        return [
            'success' => true,
            'context' => $context,
            'formatted_context' => $formattedContext,
            'sources' => $context,
        ];
    }
}
