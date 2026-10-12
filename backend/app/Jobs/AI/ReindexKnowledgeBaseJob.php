<?php

namespace App\Jobs\AI;

use App\Ai\Services\KnowledgeBaseIndexer;
use App\Models\Tenant;
use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Rebuilds the persisted knowledge base embeddings for one article (or all
 * published, AI-eligible articles).
 *
 * The job captures the tenant key at dispatch time and re-initialises tenancy
 * when handled, so queued reindexing never runs against the wrong database.
 * Articles that are deleted, archived, unpublished or internal are purged
 * instead of indexed, and embeddings are never written when the provider fails.
 */
class ReindexKnowledgeBaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected ?int $articleId = null,
        protected ?string $tenantId = null,
    ) {}

    public function handle(KnowledgeBaseIndexer $indexer): void
    {
        $tenant = null;

        if ($this->tenantId !== null) {
            $tenant = Tenant::find($this->tenantId);

            if ($tenant === null) {
                Log::warning('Knowledge base reindex skipped: tenant not found', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            Tenancy::initialize($tenant);
        }

        try {
            Log::info('Starting knowledge base reindex', [
                'article_id' => $this->articleId ?? 'all',
                'tenant_id' => $this->tenantId,
            ]);

            if ($this->articleId !== null) {
                $this->reindexOne($indexer, $this->articleId);
            } else {
                foreach (KnowledgeBaseArticle::publishedAndEligible()->get() as $article) {
                    $chunks = $indexer->reindex($article);

                    Log::info('Article reindexed', [
                        'article_id' => $article->id,
                        'chunks' => $chunks,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Let the queue retry (with backoff) rather than failing outright:
            // a transient provider outage should not permanently de-index the
            // knowledge base.
            Log::error('Knowledge base reindex failed', [
                'article_id' => $this->articleId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($tenant !== null) {
                Tenancy::end();
            }
        }
    }

    protected function reindexOne(KnowledgeBaseIndexer $indexer, int $articleId): void
    {
        $article = KnowledgeBaseArticle::withTrashed()->find($articleId);

        if ($article === null) {
            $indexer->purgeById($articleId);

            return;
        }

        if ($article->trashed() || ! $this->isEligible($article)) {
            $indexer->purge($article);

            Log::info('Article embeddings purged', ['article_id' => $article->id]);

            return;
        }

        $chunks = $indexer->reindex($article);

        Log::info('Article reindexed', [
            'article_id' => $article->id,
            'chunks' => $chunks,
        ]);
    }

    protected function isEligible(KnowledgeBaseArticle $article): bool
    {
        return $article->status === 'published'
            && in_array($article->visibility, ['ai', 'public'], true);
    }
}
