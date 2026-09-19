<?php

namespace App\Listeners\Ai;

use App\Exceptions\PlanLimitExceededException;
use App\Services\Billing\BillingLimitService;
use App\Services\Billing\UsageTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class EnforceAiBillingLimit
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected BillingLimitService $limits,
        protected UsageTracker $usage,
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $tenant = $event->tenant ?? app('current_tenant');

        if (!$tenant) {
            return;
        }

        $tokens = (int) ($event->tokens ?? 0);

        // $this->limits->enforce($tenant, 'ai.requests.monthly', 1);

        // if ($tokens > 0) {
        //     $this->limits->enforce($tenant, 'ai.tokens.monthly', $tokens);
        // }
        try {
            // Enforce AI request quota
            $this->limits->enforce($tenant, 'ai.requests.monthly', 1);

            // If tokens are provided, enforce token quota too
            if ($tokens > 0) {
                $this->limits->enforce($tenant, 'ai.tokens.monthly', $tokens);
            }

            // Track usage
            $this->usage->track($tenant->id, 'ai_requests', 1);
            if ($tokens > 0) {
                $this->usage->track($tenant->id, 'ai_tokens', $tokens);
            }
        } catch (PlanLimitExceededException $e) {
            Log::info('AI request blocked by billing limit', [
                'tenant_id' => $tenant->id,
                'code'      => $e->errorCode,
                'data'      => $e->errorData,
            ]);
            throw $e;
        }
    }
}