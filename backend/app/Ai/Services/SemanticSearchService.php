<?php
// app/Ai/Services/SemanticSearchService.php

namespace App\Ai\Services;

use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
            // Generate embedding for query
            $queryEmbedding = $this->embeddingService->generateEmbedding($query);

            // Get all published articles
            $articles = KnowledgeBaseArticle::published()
                ->whereIn('visibility', ['ai', 'public'])
                ->get();

            if ($articles->isEmpty()) {
                return [];
            }

            $results = [];

            foreach ($articles as $article) {
                // Get or generate article embeddings
                $articleEmbeddings = $this->getArticleEmbeddings($article);

                // Calculate similarity scores
                foreach ($articleEmbeddings as $chunk) {
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

        } catch (\Exception $e) {
            Log::error('Semantic search failed:', [
                'error' => $e->getMessage(),
                'query' => substr($query, 0, 100),
            ]);

            // Fallback to keyword search
            return $this->keywordSearchFallback($query, $limit);
        }
    }

    /**
     * Get or generate article embeddings
     */
    protected function getArticleEmbeddings($article): array
    {
        $cacheKey = 'article_embeddings_' . $article->id;

        if ($this->cacheResults && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Chunk the article
        $chunks = $this->chunkingService->chunkArticle($article);

        // Generate embeddings for each chunk
        $embeddings = [];
        foreach ($chunks as $chunk) {
            $embedding = $this->embeddingService->generateEmbedding($chunk['content']);
            $embeddings[] = [
                'content' => $chunk['content'],
                'embedding' => $embedding,
                'index' => $chunk['index'],
            ];
        }

        if ($this->cacheResults) {
            Cache::put($cacheKey, $embeddings, 86400); // 24 hours
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
                'relevance' => round($result['similarity'] * 100, 1) . '%',
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
            $formattedContext .= "### " . $item['title'] . "\n";
            $formattedContext .= $item['content'] . "\n\n";
        }

        return [
            'success' => true,
            'context' => $context,
            'formatted_context' => $formattedContext,
            'sources' => $context,
        ];
    }
}