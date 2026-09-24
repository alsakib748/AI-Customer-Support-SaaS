<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserListService
{
    protected const ALLOWED_SORTS = [
        'name',
        'email',
        'status',
        'last_login_at',
        'created_at',
        'updated_at',
    ];

    protected const ALLOWED_STATUSES = [
        'active',
        'suspended',
    ];

    /**
     * Paginated platform-user list with search / filter / sort.
     * Only central-DB identity data is loaded — never tenant operational data.
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->withCount('tenantUsers');

        // Search (name, email, username)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%");
            });
        }

        // Status (derived from is_active)
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        // Scope: platform users (super_admin role) vs tenant users
        if (($filters['scope'] ?? null) === 'platform') {
            $query->role('super_admin');
        } elseif (($filters['scope'] ?? null) === 'tenant') {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'));
        }

        // Verification
        if (($filters['verification'] ?? null) === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif (($filters['verification'] ?? null) === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        // Membership inside a specific tenant
        if (! empty($filters['tenant_id'])) {
            $query->whereHas('tenantUsers', fn ($q) => $q->where('tenant_id', $filters['tenant_id']));
        }

        // Date range filter
        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        // Sorting (whitelist; "name" sorts by first_name)
        $sort = in_array($filters['sort'] ?? '', self::ALLOWED_SORTS, true)
            ? $filters['sort']
            : 'created_at';

        if ($sort === 'name') {
            $sort = 'first_name';
        }

        $direction = strtolower($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Efficient aggregate counts for the Users page stat cards.
     */
    public function statistics(): array
    {
        $total     = User::query()->count();
        $active    = User::query()->where('is_active', true)->count();
        $suspended = $total - $active;
        $platform  = User::role('super_admin')->count();
        $verified  = User::query()->whereNotNull('email_verified_at')->count();

        return [
            'total'     => $total,
            'active'    => $active,
            'suspended' => $suspended,
            'platform'  => $platform,
            'tenant'    => max(0, $total - $platform),
            'verified'  => $verified,
            'unverified' => max(0, $total - $verified),
        ];
    }

    public function allowedStatuses(): array
    {
        return self::ALLOWED_STATUSES;
    }
}