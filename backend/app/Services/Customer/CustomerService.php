<?php
// app/Services/Customer/CustomerService.php

namespace App\Services\Customer;

use App\Models\Tenant\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerService
{
    protected $tenant;
    protected $tenantId;

    public function __construct(Request $request)
    {
        // Try multiple ways to get the tenant
        $tenant = null;

        // 1. From request attributes (set by TenantAware middleware)
        if ($request->attributes->has('current_tenant')) {
            $tenant = $request->attributes->get('current_tenant');
            Log::info('Tenant from attributes', ['tenant_id' => $tenant?->id]);
        }

        // 2. From authenticated user's current tenant
        if (!$tenant && auth()->check()) {
            $user = auth()->user();
            if ($user && $user->current_tenant_id) {
                $tenant = \App\Models\Tenant::find($user->current_tenant_id);
                Log::info('Tenant from user', ['tenant_id' => $tenant?->id]);
            }
        }

        // 3. From header
        if (!$tenant) {
            $tenantId = $request->header('X-Tenant-ID');
            if ($tenantId) {
                $tenant = \App\Models\Tenant::find($tenantId);
                Log::info('Tenant from header', ['tenant_id' => $tenantId]);
            }
        }

        // 4. From first tenant of user
        if (!$tenant && auth()->check()) {
            $user = auth()->user();
            if ($user) {
                $tenant = $user->tenants()->first();
                if ($tenant) {
                    $user->update(['current_tenant_id' => $tenant->id]);
                    Log::info('Tenant from first tenant', ['tenant_id' => $tenant->id]);
                }
            }
        }

        // 5. Check if user is Super Admin
        if (!$tenant && auth()->check() && auth()->user()->hasRole('super-admin')) {
            Log::info('Super Admin accessing customer service - no tenant needed');
            // For Super Admin, we can return empty data or all tenants
            // We'll handle this in the methods
            $this->tenant = null;
            $this->tenantId = null;
            return;
        }

        if (!$tenant) {
            Log::error('No tenant found in customer service', [
                'user_id' => auth()->id(),
                'headers' => $request->headers->all(),
                'attributes' => $request->attributes->all(),
            ]);
            throw new \RuntimeException('No tenant found in current context. Please select a workspace.');
        }

        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        Log::info('CustomerService initialized', ['tenant_id' => $this->tenantId]);
    }

    /**
     * Get current tenant ID
     */
    protected function getTenantId(): string
    {
        if (!$this->tenantId) {
            throw new \RuntimeException('No tenant found in current context');
        }
        return $this->tenantId;
    }

    /**
     * Check if Super Admin
     */
    protected function isSuperAdmin(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super-admin');
    }

    /**
     * Get paginated customers with filters
     */
    public function getCustomers(array $filters = [])
    {
        // If Super Admin, return all customers across all tenants or empty
        if ($this->isSuperAdmin() && !$this->tenantId) {
            Log::info('Super Admin viewing customers - returning empty or all');
            // Return empty paginated result for Super Admin without tenant
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20, 1);
        }

        $tenantId = $this->getTenantId();
        $query = Customer::query();

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by tag
        if (!empty($filters['tag'])) {
            $query->byTag($filters['tag']);
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = [
            'first_name',
            'last_name',
            'email',
            'company_name',
            'status',
            'created_at',
            'last_contacted_at',
            'total_conversations',
            'satisfaction_score',
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    /**
     * Get a single customer
     */
    public function getCustomer(int $id): Customer
    {
        $tenantId = $this->getTenantId();
        return Customer::where('id', $id)->firstOrFail();
    }

    /**
     * Get customer by UUID
     */
    public function getCustomerByUuid(string $uuid): Customer
    {
        return Customer::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        // Check if Super Admin
        // if ($this->isSuperAdmin()) {
        //     throw new \RuntimeException('Super Admin cannot create customers without tenant context.');
        // }

        $tenantId = $this->getTenantId();

        // dd($data);

        $customerData = [
            'uuid' => (string) Str::uuid(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'avatar' => $data['avatar'] ?? null,           // ✅ Changed from avatar_url
            'status' => $data['status'] ?? 'active',
            'tags' => $data['tags'] ?? [],
            'notes' => $data['notes'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'default_language' => $data['default_language'] ?? 'en',
        ];

        // Log what we're about to create
        Log::info('Creating customer with data:', $customerData);

        // dd($customerData);

        // Create customer
        $customer = Customer::create($customerData);

        Log::info('Customer created', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
        ]);

        return $customer;
    }
    /**
     * Update a customer
     */
    public function updateCustomer(int $id, array $data): Customer
    {
        $tenantId = $this->getTenantId();
        $customer = $this->getCustomer($id);

        // Remove empty values to avoid overwriting with null
        $updateData = array_filter($data, function ($value) {
            return $value !== '' && $value !== null;
        });

        $customer->update($updateData);

        Log::info('Customer updated', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
        ]);

        return $customer->fresh();
    }

    /**
     * Delete a customer (soft delete)
     */
    public function deleteCustomer(int $id): bool
    {
        $tenantId = $this->getTenantId();
        $customer = $this->getCustomer($id);
        $customer->delete();

        Log::info('Customer deleted', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Restore a deleted customer
     */
    public function restoreCustomer(int $id): Customer
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::withTrashed()->where('id', $id)->firstOrFail();
        $customer->restore();

        Log::info('Customer restored', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $customer;
    }

    /**
     * Force delete a customer
     */
    public function forceDeleteCustomer(int $id): bool
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::withTrashed()->where('id', $id)->firstOrFail();
        $customer->forceDelete();

        Log::info('Customer force deleted', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Bulk delete customers
     */
    public function bulkDelete(array $ids): int
    {
        $count = Customer::whereIn('id', $ids)->delete();

        Log::info('Customers bulk deleted', [
            'tenant_id' => $this->tenant->id,
            'count' => $count,
            'user_id' => auth()->id(),
        ]);

        return $count;
    }

    /**
     * Block a customer
     */
    public function blockCustomer(int $id, string $reason = null): Customer
    {
        $tenantId = $this->getTenantId();
        $customer = $this->getCustomer($id);
        $customer->block($reason);

        Log::info('Customer blocked', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);

        return $customer->fresh();
    }

    /**
     * Unblock a customer
     */
    public function unblockCustomer(int $id): Customer
    {
        $tenantId = $this->getTenantId();
        $customer = $this->getCustomer($id);
        $customer->unblock();

        Log::info('Customer unblocked', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $customer->fresh();
    }

    /**
     * Add a tag to a customer
     */
    public function addTag(int $id, string $tag): Customer
    {
        $tenantId = $this->getTenantId();
        $customer = $this->getCustomer($id);
        $customer->addTag($tag);

        Log::info('Tag added to customer', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'tag' => $tag,
            'user_id' => auth()->id(),
        ]);

        return $customer->fresh();
    }

    /**
     * Remove a tag from a customer
     */
    public function removeTag(int $id, string $tag): Customer
    {
        $tenantId = $this->getTenantId();
        $customer = $this->getCustomer($id);
        $customer->removeTag($tag);

        Log::info('Tag removed from customer', [
            'tenant_id' => $this->tenantId,
            'customer_id' => $id,
            'tag' => $tag,
            'user_id' => auth()->id(),
        ]);

        return $customer->fresh();
    }


    /**
     * Get customer statistics
     */
    public function getStatistics(): array
    {
        // $tenantId = $this->getTenantId();
        // $query = Customer::query();

        // // dd($tenantId);

        // $result = [
        //     'total' => $query->count(),
        //     'active' => (clone $query)->where('status', 'active')->count(),
        //     'inactive' => (clone $query)->where('status', 'inactive')->count(),
        //     'blocked' => (clone $query)->where('status', 'blocked')->count(),
        //     'new_this_week' => (clone $query)->where('created_at', '>=', now()->subWeek())->count(),
        //     'new_this_month' => (clone $query)->where('created_at', '>=', now()->subMonth())->count(),
        //     'with_conversations' => (clone $query)->where('total_conversations', '>', 0)->count(),
        // ];

        // return $result;

        try {
            // Check if Super Admin
            if ($this->isSuperAdmin() && !$this->tenantId) {
                Log::info('Super Admin viewing customer statistics - returning empty');
                return $this->getEmptyStatistics();
            }

            $tenantId = $this->getTenantId();

            $query = Customer::query();

            return [
                'total' => $query->count(),
                'active' => (clone $query)->where('status', 'active')->count(),
                'inactive' => (clone $query)->where('status', 'inactive')->count(),
                'blocked' => (clone $query)->where('status', 'blocked')->count(),
                'new_this_week' => (clone $query)->where('created_at', '>=', now()->subWeek())->count(),
                'new_this_month' => (clone $query)->where('created_at', '>=', now()->subMonth())->count(),
                'with_conversations' => (clone $query)->where('total_conversations', '>', 0)->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get customer statistics:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getEmptyStatistics();
        }

    }

    /**
     * Get all tags
     */
    public function getTags(): array
    {
        try {
            $tenantId = $this->getTenantId();

            // Check if tags column exists
            // if (!Schema::hasColumn('customers', 'tags')) {
            //     Log::warning('Tags column not found in customers table', ['tenant_id' => $tenantId]);
            //     return [];
            // }

            // Method 1: Using Laravel's JSON WHERE with PostgreSQL
            $result = Customer::whereNotNull('tags')
                ->where('tags', '!=', '[]')
                ->get()
                ->pluck('tags')
                ->flatten()
                ->unique()
                ->values()
                ->toArray();

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to get tags:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty array instead of throwing error
            return [];
        }
    }

    /**
     * Get empty statistics array
     */
    protected function getEmptyStatistics(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'blocked' => 0,
            'new_this_week' => 0,
            'new_this_month' => 0,
            'with_conversations' => 0,
        ];
    }

    /**
     * Get customers with high satisfaction (AI-ready)
     */
    public function getHighSatisfactionCustomers(int $limit = 10): array
    {
        return Customer::whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '>=', 4.5)
            ->orderBy('satisfaction_score', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get customers with low satisfaction (AI-ready)
     */
    public function getLowSatisfactionCustomers(int $limit = 10): array
    {
        return Customer::whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '<=', 2.5)
            ->orderBy('satisfaction_score', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}