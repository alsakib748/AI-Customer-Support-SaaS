<?php

namespace App\Services\Billing;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Tenant;
use App\Models\Tenant\AIUsage;
use App\Models\Tenant\ChatWidget;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\QuotaReservation;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingLimitService
{
    /**
     * Headroom used for unlimited plans so the atomic reservation UPDATE still
     * has a numeric bound on every database driver.
     */
    protected const UNLIMITED_HEADROOM = 1000000000;

    public function __construct(
        protected SubscriptionService $subscriptions,
        protected UsageTracker $usage,
    ) {}

    /**
     * Check if tenant can create N more of a resource.
     *
     * @param  string  $feature  e.g. "agents.max", "ai.requests.monthly"
     * @return array{allowed: bool, code: string|null, message: string|null, data: array}
     */
    public function check(Tenant $tenant, string $feature, int $requested = 1): array
    {
        $subscription = $this->subscriptions->getEntitlingSubscription($tenant->id);

        if (! $subscription) {
            return $this->deny(
                $feature,
                0,
                0,
                'no_active_subscription',
                'No active subscription. Please choose a plan.'
            );
        }

        $plan = $subscription->plan;

        if (! $plan) {
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

    /**
     * Atomically reserve a metered slot before the work is performed.
     *
     * Unlike check()/enforce(), which read-then-compare and race under
     * concurrency, the decision here is made by a single conditional UPDATE
     * (`reserved_count + requested <= headroom`). Returns the reservation to
     * release once the work is accounted for, or null when the limit is hit.
     */
    public function reserve(Tenant $tenant, string $feature, int $requested = 1): ?QuotaReservation
    {
        $subscription = $this->subscriptions->getEntitlingSubscription($tenant->id);

        if (! $subscription || ! $plan = $subscription->plan) {
            return null;
        }

        $limit = $plan->getLimit($feature);
        $period = now()->format('Y-m');
        $current = $this->current($tenant, $feature);

        $unlimited = ($limit === null || (int) $limit <= 0);
        $headroom = $unlimited ? self::UNLIMITED_HEADROOM : ((int) $limit - $current);

        if ($headroom < $requested) {
            return null;
        }

        try {
            QuotaReservation::query()->insertOrIgnore([
                'feature' => $feature,
                'period' => $period,
                'reserved_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Atomic claim: only the process whose update keeps the counter
            // within the remaining headroom wins; everyone else sees 0 rows.
            $affected = QuotaReservation::query()
                ->where('feature', $feature)
                ->where('period', $period)
                ->where('reserved_count', '<=', $headroom - $requested)
                ->update([
                    'reserved_count' => DB::raw('reserved_count + '.(int) $requested),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return null;
            }

            return QuotaReservation::query()
                ->where('feature', $feature)
                ->where('period', $period)
                ->first();

        } catch (\Throwable $e) {
            Log::error('Quota reservation failed', [
                'feature' => $feature,
                'error' => $e->getMessage(),
            ]);

            // Fail closed: a reservation we cannot record must not allow spend.
            return null;
        }
    }

    /**
     * Reserve or throw the structured plan-limit exception.
     */
    public function reserveOrThrow(Tenant $tenant, string $feature, int $requested = 1): QuotaReservation
    {
        $reservation = $this->reserve($tenant, $feature, $requested);

        if ($reservation === null) {
            $result = $this->check($tenant, $feature, $requested);

            throw new PlanLimitExceededException(
                $result['message'] ?? 'Plan limit reached.',
                $result['code'] ?? 'PLAN_LIMIT_REACHED',
                $result['data']
            );
        }

        return $reservation;
    }

    /**
     * Release a previously acquired reservation once real usage has been
     * recorded (which then counts toward `current()`).
     */
    public function release(?QuotaReservation $reservation, int $requested = 1): void
    {
        if (! $reservation || ! $reservation->id) {
            return;
        }

        try {
            QuotaReservation::query()
                ->whereKey($reservation->id)
                ->where('reserved_count', '>=', $requested)
                ->decrement('reserved_count', $requested);

            QuotaReservation::query()
                ->whereKey($reservation->id)
                ->where('reserved_count', '<=', 0)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Quota reservation release failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
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
        $subscription = $this->subscriptions->getEntitlingSubscription($tenant->id);

        if (! $subscription || ! $subscription->plan) {
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

        if (! $result['allowed']) {
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
            // Agents = accepted members + pending invitations (central DB)
            'agents.max' => $this->countAgents($tenant),

            // Tenant-scoped resources live in the tenant database and are already
            // isolated per tenant by the connection — do NOT filter by tenant_id
            // (those tables have no such column).
            'customers.max' => $this->safeCount(fn () => Customer::count()),

            'widgets.max' => $this->safeCount(fn () => ChatWidget::count()),

            'kb.articles.max' => $this->safeCount(fn () => KnowledgeBaseArticle::count()),

            // Conversations this month
            'conversations.monthly' => $this->safeCount(fn () => Conversation::where('created_at', '>=', now()->startOfMonth())->count()),

            // AI requests this month. In-flight claims (metadata.status =
            // "processing") are tracked by quota_reservations instead, so they are
            // excluded here to avoid counting the same request twice.
            'ai.requests.monthly' => $this->safeCount(fn () => AIUsage::where('created_at', '>=', now()->startOfMonth())
                ->where(function ($query) {
                    $query->whereNull('metadata')
                        ->orWhere('metadata->status', '!=', 'processing');
                })
                ->count()),

            // AI tokens this month (see note above on in-flight claims)
            'ai.tokens.monthly' => $this->safeCount(fn () => (int) AIUsage::where('created_at', '>=', now()->startOfMonth())
                ->where(function ($query) {
                    $query->whereNull('metadata')
                        ->orWhere('metadata->status', '!=', 'processing');
                })
                ->sum('total_tokens')),

            // Storage bytes are synced from the tenant's stored documents by
            // UsageTracker::syncStorageUsage and cached on the subscription.
            'storage_bytes' => (int) ($this->subscriptions
                ->getEntitlingSubscription($tenant->id)?->storage_used ?? 0),

            default => 0,
        };
    }

    /**
     * Count agents = accepted members + pending invitations.
     * Prevents over-inviting beyond plan capacity.
     */
    protected function countAgents(Tenant $tenant): int
    {
        $members = TenantUser::where('tenant_id', $tenant->id)->count();

        $pendingInvites = TenantInvitation::where('tenant_id', $tenant->id)
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
            return AIUsage::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
        });
    }

    protected function countMonthlyAiTokens(Tenant $tenant): int
    {
        return $this->safeCount(function () use ($tenant) {
            return (int) AIUsage::where('tenant_id', $tenant->id)
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
