<?php

namespace App\Jobs\AI;

use App\Ai\Exceptions\EmbeddingException;
use App\Ai\Services\EmbeddingService;
use App\Ai\Services\KnowledgeBaseChunkingService;
use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReindexKnowledgeBaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    protected $articleId;

    public function __construct(?int $articleId = null)
    {
        $this->articleId = $articleId;
    }

    public function handle(
        EmbeddingService $embeddingService,
        KnowledgeBaseChunkingService $chunkingService
    ): void {
        try {
            Log::info('Starting knowledge base reindex', [
                'article_id' => $this->articleId ?? 'all',
            ]);

            if ($this->articleId) {
                // Reindex specific article
                $articles = KnowledgeBaseArticle::where('id', $this->articleId)->get();
            } else {
                // Reindex all published articles
                $articles = KnowledgeBaseArticle::published()
                    ->whereIn('visibility', ['ai', 'public'])
                    ->get();
            }

            foreach ($articles as $article) {
                // Chunk and generate embeddings
                $chunks = $chunkingService->chunkArticle($article);

                if ($chunks === []) {
                    continue;
                }

                try {
                    $vectors = $embeddingService->batchGenerateEmbeddings(
                        array_map(fn ($chunk) => $chunk['content'], $chunks),
                        'RETRIEVAL_DOCUMENT'
                    );
                } catch (EmbeddingException $e) {
                    // Leave the previous revision in cache; never store fabricated vectors.
                    Log::warning('Skipping article reindex after embedding failure', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                $embeddings = [];
                foreach ($chunks as $position => $chunk) {
                    $embeddings[] = [
                        'content' => $chunk['content'],
                        'embedding' => $vectors[$position] ?? [],
                        'index' => $chunk['index'],
                        'content_hash' => sha1($chunk['content']),
                    ];
                }

                // Store embeddings under the tenant/model/version-scoped key.
                Cache::put(
                    $embeddingService->articleCacheKey(
                        $article->id,
                        (string) $article->title,
                        (string) $article->content,
                        $article->updated_at?->timestamp
                    ),
                    $embeddings,
                    (int) config('ai.embeddings.cache_ttl', 86400)
                );

                Log::info('Article reindexed', [
                    'article_id' => $article->id,
                    'chunks' => count($chunks),
                ]);
            }

            Log::info('Knowledge base reindex completed', [
                'articles' => $articles->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Reindex failed:', [
                'error' => $e->getMessage(),
                'article_id' => $this->articleId,
            ]);

            $this->fail($e);
        }
    }
}
