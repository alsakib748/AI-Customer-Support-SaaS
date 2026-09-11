<?php

namespace App\Jobs\AI;

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
                // Clear cached embeddings
                Cache::forget('article_embeddings_' . $article->id);

                // Chunk and generate embeddings
                $chunks = $chunkingService->chunkArticle($article);
                $embeddings = [];

                foreach ($chunks as $chunk) {
                    $embedding = $embeddingService->generateEmbedding($chunk['content']);
                    $embeddings[] = [
                        'content' => $chunk['content'],
                        'embedding' => $embedding,
                        'index' => $chunk['index'],
                    ];
                }

                // Store embeddings
                Cache::put('article_embeddings_' . $article->id, $embeddings, 86400);

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