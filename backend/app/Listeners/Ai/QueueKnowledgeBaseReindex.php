<?php

namespace App\Listeners\Ai;

use App\Jobs\AI\ReindexKnowledgeBaseJob;
use Illuminate\Support\Facades\Log;

/**
 * Queues a knowledge base reindex whenever an article is created, updated,
 * published, unpublished, archived or deleted.
 *
 * The listener runs inside the tenant context (articles are tenant models), so
 * the tenant key is captured and handed to the job, which re-initialises
 * tenancy when it is processed by a queue worker.
 */
class QueueKnowledgeBaseReindex
{
    public function handle(object $event): void
    {
        $article = data_get($event, 'article');

        if ($article === null || ! isset($article->id)) {
            return;
        }

        $tenantId = optional(tenancy()->tenant)->getTenantKey()
            ?? (app()->bound('current_tenant') ? optional(app('current_tenant'))->getTenantKey() : null);

        if ($tenantId === null) {
            Log::warning('Knowledge base reindex skipped: no tenant context', [
                'article_id' => $article->id,
                'event' => get_class($event),
            ]);

            return;
        }

        ReindexKnowledgeBaseJob::dispatch($article->id, (string) $tenantId);
    }
}
