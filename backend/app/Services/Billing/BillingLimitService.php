<?php

namespace App\Services\Billing;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Tenant\AIUsage;
use App\Models\Tenant\ChatWidget;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BillingLimitService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected UsageTracker $usage,
    ) {
    }

    /**
     * Check if tenant can create N more of a resource.
     *
     * @param  string  $feature  e.g. "agents.max", "ai.requests.monthly"
     * @return array{allowed: bool, code: string|null, message: string|null, data: array}
     */
    public function check(Tenant $tenant, string $feature, int $requested = 1): array
    {
        $subscription = $this->subscriptions->getActiveSubscription($tenant->id);

        if (!$subscription) {
            return $this->deny(
                $feature,
                0,
                0,
                'no_active_subscription',
                'No active subscription. Please choose a plan.'
            );
        }

        $plan = $subscription->plan;

        if (!$plan) {
            return $this->deny($feature, 0, 0, 'no_plan', 'Subscription has no plan attached.');
        }

        $limit = $plan->getLimit($feature);

        // null / <= 0 means unlimited (per plan convention)
        if ($limit === null || (int) $limit <= 0) {
            return $this->allow($feature, null, $this->current($tenant, $feature));
        }

        $current = $this->current($tenant, $feature);

        if (($current + $requested) > (int) $limit) {
            return $this->deny(
                $feature,
                (int) $limit,
                $current,
                'PLAN_LIMIT_REACHED',
                "You have reached your plan limit for {$feature}."
            );
        }

        return $this->allow($feature, (int) $limit, $current);
    }

    public function canCreateAgent(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'agents.max', $requested);
    }

    public function canCreateWidget(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'widgets.max', $requested);
    }

    public function canCreateCustomer(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'customers.max', $requested);
    }

    public function canCreateKnowledgeBaseArticle(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'kb.articles.max', $requested);
    }

    public function canConsumeAiRequests(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'ai.requests.monthly', $requested);
    }

    public function canConsumeAiTokens(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'ai.tokens.monthly', $requested);
    }

    public function canStartConversation(Tenant $tenant, int $requested = 1): array
    {
        return $this->check($tenant, 'conversations.monthly', $requested);
    }

    public function canUseFeature(Tenant $tenant, string $feature): bool
    {
        $subscription = $this->subscriptions->getActiveSubscription($tenant->id);

        if (!$subscription || !$subscription->plan) {
            return false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    /**
     * Throw a structured exception (use in controllers).
     */
    public function enforce(Tenant $tenant, string $feature, int $requested = 1): void
    {
        $result = $this->check($tenant, $feature, $requested);

        if (!$result['allowed']) {
            throw new PlanLimitExceededException(
                $result['message'] ?? 'Plan limit reached.',
                $result['code'] ?? 'PLAN_LIMIT_REACHED',
                $result['data']
            );
        }
    }

    // protected function current(Tenant $tenant, string $feature): int
    // {
    //     // Metric keys used by UsageTracker
    //     $map = [
    //         'agents.max' => 'agents',
    //         'widgets.max' => 'widgets',
    //         'customers.max' => 'customers',
    //         'kb.articles.max' => 'kb_articles',
    //         'conversations.monthly' => 'conversations',
    //         'ai.requests.monthly' => 'ai_requests',
    //         'ai.tokens.monthly' => 'ai_tokens',
    //     ];

    //     $metric = $map[$feature] ?? $feature;

    //     return (int) $this->usage->currentUsage($tenant->id, $metric);
    // }


    protected function current(Tenant $tenant, string $feature): int
{
    return match ($feature) {
        // Agents = accepted members + pending invitations
        'agents.max' => $this->countAgents($tenant),

        // Customers
        'customers.max' => Customer::where('tenant_id', $tenant->id)->count(),

        // Widgets
        'widgets.max' => ChatWidget::where('tenant_id', $tenant->id)->count(),

        // Knowledge Base articles
        'kb.articles.max' => KnowledgeBaseArticle::where('tenant_id', $tenant->id)->count(),

        // Conversations this month
        'conversations.monthly' => Conversation::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count(),

        // AI requests this month
        'ai.requests.monthly' => AIUsage::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count(),

        // AI tokens this month
        'ai.tokens.monthly' => (int) AIUsage::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('tokens_used'),

        default => 0,
    };
}

/**
 * Count agents = accepted members + pending invitations.
 * Prevents over-inviting beyond plan capacity.
 */
protected function countAgents(Tenant $tenant): int
{
    $members = \App\Models\TenantUser::where('tenant_id', $tenant->id)->count();

    $pendingInvites = \App\Models\TenantInvitation::where('tenant_id', $tenant->id)
        ->whereNull('accepted_at')
        ->whereNull('revoked_at')
        ->where('expires_at', '>', now())
        ->count();

    return $members + $pendingInvites;
}

    protected function allow(string $feature, ?int $limit, int $current): array
    {
        return [
            'allowed' => true,
            'code' => null,
            'message' => null,
            'data' => [
                'feature' => $feature,
                'limit' => $limit,
                'current' => $current,
                'upgrade_required' => false,
            ],
        ];
    }

    protected function deny(string $feature, int $limit, int $current, string $code, string $message): array
    {
        return [
            'allowed' => false,
            'code' => $code,
            'message' => $message,
            'data' => [
                'feature' => $feature,
                'limit' => $limit,
                'current' => $current,
                'upgrade_required' => true,
            ],
        ];
    }

    protected function countCustomers(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return Customer::where('tenant_id', $tenant->id)->count();
        });
    }

    protected function countWidgets(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return ChatWidget::where('tenant_id', $tenant->id)->count();
        });
    }

    protected function countKbArticles(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return KnowledgeBaseArticle::where('tenant_id', $tenant->id)->count();
        });
    }

    protected function countMonthlyConversations(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return Conversation::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
        });
    }

    protected function countMonthlyAiRequests(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return AiUsage::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
        });
    }

    protected function countMonthlyAiTokens(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return (int) AiUsage::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('tokens_used');
        });
    }

    /**
     * Wrap counts in try/catch so a missing table or connection issue
     * never crashes the whole request. Returns 0 on failure.
     */
    protected function safeCount(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            Log::warning('BillingLimitService count failed', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

}
