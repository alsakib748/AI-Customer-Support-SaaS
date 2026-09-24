<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantListService
{
    protected const ALLOWED_SORTS = [
        'name',
        'slug',
        'status',
        'created_at',
        'updated_at',
    ];

    protected const ALLOWED_STATUSES = [
        'trial',
        'active',
        'suspended',
        'archived',
        'provisioning',
        'provisioning_failed',
    ];

    /**
     * Paginated tenant list with search / filter / sort.
     * Only platform-level (central DB) data is loaded here — never tenant data.
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Tenant::query()
            ->with([
                'ownerMembership.user',
                'activeSubscription.plan',
            ])
            ->withCount('members');

        // Search (name, slug, owner name, owner email)
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Plan filter
        if (! empty($filters['plan_id'])) {
            $query->withPlan($filters['plan_id']);
        }

        // Subscription status filter
        if (! empty($filters['subscription_status'])) {
            $query->withSubscriptionStatus($filters['subscription_status']);
        }

        // Date range filter
        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        // Sorting (whitelist)
        $sort = in_array($filters['sort'] ?? '', self::ALLOWED_SORTS, true)
            ? $filters['sort']
            : 'created_at';

        $direction = strtolower($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage)
            ->withQueryString();
    }

    public function allowedStatuses(): array
    {
        return self::ALLOWED_STATUSES;
    }
}