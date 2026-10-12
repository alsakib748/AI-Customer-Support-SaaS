<?php

// app/Services/Billing/UsageTracker.php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

class UsageTracker
{
    protected const CACHE_TTL = 300; // 5 minutes

    /**
     * Track usage for a tenant
     */
    public function track(
        string $tenantId,
        string $type,
        int $quantity = 1,
        array $metadata = []
    ): void {
        try {
            $subscription = $this->getSubscription($tenantId);

            if (! $subscription) {
                Log::warning('No subscription found for usage tracking', [
                    'tenant_id' => $tenantId,
                    'type' => $type,
                ]);

                return;
            }

            // Increment subscription usage
            $subscription->incrementUsage($type, $quantity);

            // Record in usage_records for analytics
            UsageRecord::record(
                $tenantId,
                $type,
                $quantity,
                $subscription->id
            );

            // Clear cache
            Cache::forget("subscription_usage:{$tenantId}");

            Log::info('Usage tracked', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'quantity' => $quantity,
            ]);

        } catch (\Exception $e) {
            Log::error('Usage tracking failed', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if tenant has reached limit
     */
    public function hasReachedLimit(string $tenantId, string $type): bool
    {
        $subscription = $this->getSubscription($tenantId);

        if (! $subscription) {
            return true; // No subscription = no access
        }

        return $subscription->hasReachedLimit($type);
    }

    /**
     * Get remaining quota
     */
    public function getRemaining(string $tenantId, string $type): int
    {
        $subscription = $this->getSubscription($tenantId);

        if (! $subscription) {
            return 0;
        }

        $used = $subscription->{"{$type}_used"} ?? 0;
        $limit = $subscription->{"{$type}_limit"} ?? 0;

        if ($limit <= 0) {
            return PHP_INT_MAX; // Unlimited
        }

        return max(0, $limit - $used);
    }

    /**
     * Get usage summary
     */
    public function getSummary(string $tenantId): array
    {
        return Cache::remember("subscription_usage:{$tenantId}", self::CACHE_TTL, function () use ($tenantId) {
            $subscription = $this->getSubscription($tenantId);

            if (! $subscription) {
                return [];
            }

            return [
                'ai' => [
                    'used' => $subscription->ai_used,
                    'limit' => $subscription->ai_limit,
                    'remaining' => $this->getRemaining($tenantId, 'ai'),
                    'percentage' => $subscription->usage_percentage['ai'] ?? 0,
                ],
                'agents' => [
                    'used' => $subscription->agents_used,
                    'limit' => $subscription->agents_limit,
                    'remaining' => $this->getRemaining($tenantId, 'agents'),
                    'percentage' => $subscription->usage_percentage['agents'] ?? 0,
                ],
                'documents' => [
                    'used' => $subscription->documents_used,
                    'limit' => $subscription->documents_limit,
                    'remaining' => $this->getRemaining($tenantId, 'documents'),
                    'percentage' => $subscription->usage_percentage['documents'] ?? 0,
                ],
                'storage' => [
                    'used' => $subscription->storage_used,
                    'limit' => $subscription->storage_limit,
                    'remaining' => $this->getRemaining($tenantId, 'storage'),
                    'percentage' => $subscription->usage_percentage['storage'] ?? 0,
                ],
                'conversations' => [
                    'used' => $subscription->conversations_used,
                    'limit' => $subscription->conversations_limit,
                    'remaining' => $this->getRemaining($tenantId, 'conversations'),
                    'percentage' => $subscription->usage_percentage['conversations'] ?? 0,
                ],
            ];
        });
    }

    public function currentUsage(string $tenantId, string $metric): int
    {
        $subscription = $this->getSubscription($tenantId);

        if (! $subscription) {
            return 0;
        }

        $field = "{$metric}_used";

        return (int) ($subscription->{$field} ?? 0);
    }

    /**
     * Sync storage usage
     */
    public function syncStorageUsage(string $tenantId): void
    {
        $subscription = $this->getSubscription($tenantId);

        if (! $subscription) {
            return;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            Log::warning('Storage usage sync skipped: tenant not found', [
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        // Tenant content lives in the tenant's own database, so the tenant
        // connection must be pointed at it before measuring.
        $startedTenancy = false;

        try {
            if (! tenancy()->initialized) {
                Tenancy::initialize($tenant);
                $startedTenancy = true;
            }

            $bytes = $this->calculateStorageBytes($tenantId);

            $subscription->update(['storage_used' => $bytes]);

            Cache::forget("subscription_usage:{$tenantId}");

            Log::info('Storage usage synced', [
                'tenant_id' => $tenantId,
                'bytes' => $bytes,
            ]);
        } catch (\Throwable $e) {
            Log::error('Storage usage sync failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($startedTenancy && tenancy()->initialized) {
                Tenancy::end();
            }
        }
    }

    /**
     * Total bytes of the tenant's stored documents.
     *
     * Knowledge base articles live in the tenant database, which is already
     * isolated per tenant, so there is no tenant_id column to filter on.
     * Byte length is measured in PHP so it is exact on every database driver
     * (SQL LENGTH counts characters on SQLite/PostgreSQL, not bytes).
     */
    protected function calculateStorageBytes(string $tenantId): int
    {
        $total = 0;

        DB::connection('tenant')
            ->table('knowledge_base_articles')
            ->select('id', 'title', 'excerpt', 'content')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(200, function ($articles) use (&$total) {
                foreach ($articles as $article) {
                    $total += strlen((string) $article->title)
                        + strlen((string) $article->excerpt)
                        + strlen((string) $article->content);
                }
            });

        return $total;
    }

    /**
     * Reset monthly usage counters
     */
    public function resetMonthlyUsage(string $tenantId): void
    {
        $subscription = $this->getSubscription($tenantId);
        if (! $subscription) {
            return;
        }

        $subscription->resetUsage();

        Cache::forget("subscription_usage:{$tenantId}");

        Log::info('Monthly usage reset', ['tenant_id' => $tenantId]);
    }

    /**
     * Get subscription with cache
     */
    protected function getSubscription(string $tenantId): ?Subscription
    {
        return Subscription::forTenant($tenantId)
            ->active()
            ->first();
    }
}
