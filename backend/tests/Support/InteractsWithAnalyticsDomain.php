<?php

namespace Tests\Support;

use App\Models\Subscription;
use App\Models\Tenant\AnalyticsExport;
use App\Models\Tenant as TenantModel;
use App\Models\User;

/**
 * Helpers for the tenant-scoped analytics + export API surface.
 *
 * Analytics exports live in the tenant database and are owned by a user;
 * aggregation endpoints themselves rely on PostgreSQL-only SQL and are covered
 * separately (PostgreSQL-gated) rather than here.
 */
trait InteractsWithAnalyticsDomain
{
    /**
     * @return array{0: TenantModel, 1: User, 2: Subscription}
     */
    protected function bootAnalyticsWorkspace(string $role = 'owner'): array
    {
        $tenant = $this->newTenant();
        $user = $this->newTenantUser($tenant, $role);
        $subscription = $this->newSubscription($tenant);

        $this->prepareTenantDatabase($tenant);
        $this->establishTenantContext($tenant, $user);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        return [$tenant, $user, $subscription];
    }

    protected function seedAnalyticsExport(TenantModel $tenant, int $userId, array $overrides = []): AnalyticsExport
    {
        return AnalyticsExport::query()->create(array_merge([
            'user_id' => $userId,
            'type' => 'conversations',
            'format' => 'csv',
            'status' => 'pending',
            'filters' => ['type' => 'conversations'],
        ], $overrides));
    }
}
