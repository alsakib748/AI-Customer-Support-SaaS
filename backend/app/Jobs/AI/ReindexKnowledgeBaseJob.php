<?php

namespace App\Jobs\AI;

use App\Ai\Exceptions\EmbeddingException;
use App\Ai\Services\KnowledgeBaseIndexer;
use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(KnowledgeBaseIndexer $indexer): void
    {
        try {
            Log::info('Starting knowledge base reindex', [
                'article_id' => $this->articleId ?? 'all',
            ]);

            if ($this->articleId) {
                $articles = KnowledgeBaseArticle::where('id', $this->articleId)->get();
            } else {
                $articles = KnowledgeBaseArticle::publishedAndEligible()->get();
            }

            foreach ($articles as $article) {
                try {
                    $chunks = $indexer->reindex($article);

                    Log::info('Article reindexed', [
                        'article_id' => $article->id,
                        'chunks' => $chunks,
                    ]);
                } catch (EmbeddingException $e) {
                    // Never persist fabricated vectors: leave the previous
                    // revision in place and let the job retry.
                    Log::warning('Skipping article reindex after embedding failure', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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
