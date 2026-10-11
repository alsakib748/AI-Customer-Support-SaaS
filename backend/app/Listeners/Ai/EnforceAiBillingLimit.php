<?php

namespace App\Listeners\Ai;

use App\Exceptions\PlanLimitExceededException;
use App\Services\Billing\BillingLimitService;
use Illuminate\Support\Facades\Log;

class EnforceAiBillingLimit
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected BillingLimitService $limits,
    ) {
        //
    }

    /**
     * Audit an already-generated AI response against the tenant's plan limits.
     *
     * The hard, pre-flight enforcement runs in ProcessCustomerMessage BEFORE the
     * AI provider is called (so over-quota requests never spend tokens). This
     * listener runs after the response is generated and therefore must not
     * throw — doing so would fail an otherwise-successful generation. It records
     * any breach for observability instead.
     */
    public function handle(object $event): void
    {
        $tenant = data_get($event, 'tenant')
            ?? data_get($event, 'conversation.tenant')
            ?? tenancy()->tenant
            ?? app('current_tenant');

        if (! $tenant) {
            return;
        }

        $tokens = (int) data_get($event, 'metadata.usage.total_tokens', 0);

        try {
            $this->limits->enforce($tenant, 'ai.requests.monthly', 1);

            if ($tokens > 0) {
                $this->limits->enforce($tenant, 'ai.tokens.monthly', $tokens);
            }
        } catch (PlanLimitExceededException $e) {
            Log::warning('AI response exceeded plan limit after generation', [
                'tenant_id' => $tenant->id,
                'code'      => $e->errorCode,
                'data'      => $e->errorData,
            ]);
        }
    }
}
