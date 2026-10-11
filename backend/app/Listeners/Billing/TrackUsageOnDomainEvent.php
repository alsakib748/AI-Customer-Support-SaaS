<?php

namespace App\Listeners\Billing;

use App\Events\Conversation\ConversationCreated;
use App\Events\KnowledgeBase\ArticleCreated;
use App\Events\Message\MessageCreated;
use App\Services\Billing\UsageTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TrackUsageOnDomainEvent
{
    /** @var array<class-string, array{metric:string, amount_field:string}> */
    protected array $map = [
        MessageCreated::class      => ['metric' => 'messages', 'amount_field' => 'count'],
        ConversationCreated::class => ['metric' => 'conversations', 'amount_field' => 'count'],
        ArticleCreated::class      => ['metric' => 'kb_articles', 'amount_field' => 'count'],
    ];

    /**
     * Create the event listener.
     */
    public function __construct(
        protected UsageTracker $usage,
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $class = get_class($event);

        if (!isset($this->map[$class])) {
            return;
        }

        $config = $this->map[$class];
        $amount = (int) data_get($event, $config['amount_field'], 1);

        if ($amount <= 0) {
            $amount = 1;
        }

        $tenantId = data_get($event, 'tenant.id')
            ?? data_get($event, 'tenantId')
            ?? data_get($event, 'tenant_id')
            ?? optional(tenancy()->tenant)->id
            ?? optional(app('current_tenant'))->id;

        if (!$tenantId) {
            return;
        }

        try {
            $this->usage->track($tenantId, $config['metric'], $amount);
        } catch (\Throwable $e) {
            Log::error('Usage tracking failed', [
                'event' => $class,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}