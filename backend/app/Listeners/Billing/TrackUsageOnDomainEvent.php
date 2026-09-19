<?php

namespace App\Listeners\Billing;

use App\Events\Message\MessageCreated;
use App\Services\Billing\UsageTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TrackUsageOnDomainEvent
{
    /** @var array<class-string, array{metric:string, amount_field:string}> */
    protected array $map = [
        \App\Events\Messages\MessageCreated::class => ['metric' => 'messages', 'amount_field' => 'count'],
        \App\Events\Conversations\ConversationStarted::class => ['metric' => 'conversations', 'amount_field' => 'count'],
        \App\Events\Customers\CustomerCreated::class => ['metric' => 'customers', 'amount_field' => 'count'],
        \App\Events\Team\AgentCreated::class => ['metric' => 'agents', 'amount_field' => 'count'],
        \App\Events\Widgets\WidgetCreated::class => ['metric' => 'widgets', 'amount_field' => 'count'],
        \App\Events\KnowledgeBase\ArticleCreated::class => ['metric' => 'kb_articles', 'amount_field' => 'count'],
        \App\Events\Ai\AiResponseGenerated::class => ['metric' => 'ai_requests', 'amount_field' => 'count'],
        \App\Events\Ai\AiTokensConsumed::class => ['metric' => 'ai_tokens', 'amount_field' => 'tokens'],
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