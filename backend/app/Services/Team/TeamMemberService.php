<?php
// app/Services/Team/TeamMemberService.php

namespace App\Services\Team;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TeamMemberService
{
    protected ?Tenant $tenant;
    protected ?User $user;
    protected ?string $tenantId;

    protected bool $isSuperAdmin = false;

    public function __construct(Request $request)
    {
        $this->user = auth()->user();

        // Check if user is Super Admin
        if ($this->user && $this->user->hasRole('super-admin')) {
            $this->isSuperAdmin = true;
            Log::info('Super Admin accessing team service', ['user_id' => $this->user->id]);
            // Super Admin doesn't need a specific tenant
            return;
        }

        // Log::info('TeamMemberService constructor', [
        //     'user_id' => $this->user?->id,
        //     'user_email' => $this->user?->email,
        //     'has_tenant_middleware' => $request->attributes->has('current_tenant'),
        //     'request_headers' => $request->headers->all(),
        // ]);

        $tenant = $request->attributes->get('current_tenant');

        if (!$tenant) {
            $tenantId = $request->header('X-Tenant-Id')
                ?? $request->header('X-Tenant-ID')
                ?? $request->header('x-tenant-id');

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                Log::info('Tenant from header', ['tenant_id' => $tenantId]);
            }
        }

        if (!$tenant && $this->user) {
            $tenant = $this->user->tenants()->first();
            Log::info('Tenant from first tenant', ['tenant_id' => $tenant?->id]);
        }

        if (!$tenant) {
            throw new \RuntimeException('No workspace found. Please create or select a workspace.');
        }

        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        Log::info('TeamMemberService initialized', ['tenant_id' => $this->tenantId]);
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
     * Check if current user has permission using Spatie
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->user) {
            return false;
        }

        // Super admin has all permissions
        if ($this->user->hasRole('super-admin')) {
            return true;
        }

        // Check if user has the permission
        return $this->user->hasPermissionTo($permission);
    }

    /**
     * Check if current user can manage team
     */
    public function canManageTeam(): bool
    {
        if (!$this->user) {
            return false;
        }

        // Super admin can manage any team
        if ($this->user->hasRole('super-admin')) {
            return true;
        }

        // Check if user has any team management permission
        return $this->user->hasAnyPermission([
            'team.view',
            'team.invite',
            'team.update',
            'team.remove',
            'members.view',
            'members.create',
            'members.update',
            'members.delete',
        ]);
    }

    /**
     * Check if current user can view members
     */
    public function canViewMembers(): bool
    {
        if (!$this->user) {
            return false;
        }

        // Super Admin can view all members
        if ($this->isSuperAdmin) {
            return true;
        }

        // Check if user has any team viewing permission
        return $this->user->hasAnyPermission([
            'members.view',
            'team.view',
        ]);
    }


    /**
     * Check if current user can invite members
     */
    public function canInviteMembers(): bool
    {
        return $this->hasPermission('team.invite');
    }

    /**
     * Check if current user can update members
     */
    public function canUpdateMembers(): bool
    {
        return $this->hasPermission('members.update');
    }

    /**
     * Check if current user can delete members
     */
    public function canDeleteMembers(): bool
    {
        return $this->hasPermission('members.delete');
    }

    /**
     * Get paginated team members
     */
    // public function getMembers(array $filters = [])
    // {
    //     $query = TenantUser::query()
    //         ->forTenant($this->getTenantId())
    //         ->with('user');

    //     // Search
    //     if (!empty($filters['search'])) {
    //         $query->search($filters['search']);
    //     }

    //     // Filter by department
    //     if (!empty($filters['department'])) {
    //         $query->where('department', $filters['department']);
    //     }

    //     // Filter by availability status
    //     if (!empty($filters['availability_status'])) {
    //         $query->where('availability_status', $filters['availability_status']);
    //     }

    //     // Filter by role
    //     if (!empty($filters['role'])) {
    //         $query->where('role', $filters['role']);
    //     }

    //     // Sorting
    //     $sortField = $filters['sort'] ?? 'created_at';
    //     $sortDirection = $filters['direction'] ?? 'desc';

    //     $allowedSorts = ['name', 'email', 'department', 'position', 'availability_status', 'created_at', 'role'];

    //     if (in_array($sortField, $allowedSorts)) {
    //         if ($sortField === 'name' || $sortField === 'email') {
    //             $query->whereHas('user', function ($q) use ($sortField, $sortDirection) {
    //                 $q->orderBy($sortField, $sortDirection);
    //             });
    //         } else {
    //             $query->orderBy($sortField, $sortDirection);
    //         }
    //     } else {
    //         $query->orderBy('created_at', 'desc');
    //     }

    //     $perPage = $filters['per_page'] ?? 20;
    //     return $query->paginate($perPage);
    // }

    public function getMembers(array $filters = [])
    {
        try {
            $query = TenantUser::query()->with('user');

            // If Super Admin, show all members across all tenants
            if ($this->isSuperAdmin) {
                Log::info('Super Admin viewing all team members', ['user_id' => $this->user->id]);

                // Optional: Add tenant filter if specified
                if (!empty($filters['tenant_id'])) {
                    $query->where('tenant_id', $filters['tenant_id']);
                }

                // Add tenant relationship for display
                $query->with('tenant');

                return $this->applyFiltersAndPagination($query, $filters);
            }

            // Normal users - only their tenant
            $tenantId = $this->getTenantId();
            $query->where('tenant_id', $tenantId);

            return $this->applyFiltersAndPagination($query, $filters);

            // // Search
            // if (!empty($filters['search'])) {
            //     $search = $filters['search'];
            //     $query->where(function ($q) use ($search) {
            //         $q->whereHas('user', function ($userQuery) use ($search) {
            //             $userQuery->where('first_name', 'ILIKE', "%{$search}%")
            //                 ->orWhere('last_name', 'ILIKE', "%{$search}%")
            //                 ->orWhere('email', 'ILIKE', "%{$search}%");
            //         })
            //             ->orWhere('department', 'ILIKE', "%{$search}%")
            //             ->orWhere('position', 'ILIKE', "%{$search}%");
            //     });
            // }

            // // Filter by department
            // if (!empty($filters['department'])) {
            //     $query->where('department', $filters['department']);
            // }

            // // Filter by availability status
            // if (!empty($filters['availability_status'])) {
            //     $query->where('availability_status', $filters['availability_status']);
            // }

            // // Filter by role
            // if (!empty($filters['role'])) {
            //     $query->where('role', $filters['role']);
            // }

            // // Sorting
            // $sortField = $filters['sort'] ?? 'created_at';
            // $sortDirection = $filters['direction'] ?? 'desc';

            // $allowedSorts = ['name', 'email', 'department', 'position', 'availability_status', 'created_at', 'role'];

            // if (in_array($sortField, $allowedSorts)) {
            //     if ($sortField === 'name' || $sortField === 'email') {
            //         $query->whereHas('user', function ($q) use ($sortField, $sortDirection) {
            //             $q->orderBy($sortField, $sortDirection);
            //         });
            //     } else {
            //         $query->orderBy($sortField, $sortDirection);
            //     }
            // } else {
            //     $query->orderBy('created_at', 'desc');
            // }

            // $perPage = $filters['per_page'] ?? 20;

            // // dd($perPage);

            // $result = $query->paginate($perPage);

            // // dd($result);

            // Log::info('Members fetched successfully', ['count' => $result->count()]);

            // return $result;

        } catch (\Exception $e) {
            Log::error('Failed to get members:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Apply filters and pagination to query
     */
    protected function applyFiltersAndPagination($query, array $filters = [])
    {
        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%");
                })
                    ->orWhere('department', 'ILIKE', "%{$search}%")
                    ->orWhere('position', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by department
        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        // Filter by availability status
        if (!empty($filters['availability_status'])) {
            $query->where('availability_status', $filters['availability_status']);
        }

        // Filter by role
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        // Filter by tenant (for Super Admin)
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = ['name', 'email', 'department', 'position', 'availability_status', 'created_at', 'role', 'tenant_id'];

        if (in_array($sortField, $allowedSorts)) {
            if ($sortField === 'name' || $sortField === 'email') {
                $query->whereHas('user', function ($q) use ($sortField, $sortDirection) {
                    $q->orderBy($sortField, $sortDirection);
                });
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    /**
     * Get a single team member
     */
    public function getMember(int $id): TenantUser
    {
        $query = TenantUser::query()->with('user');

        // Super Admin can access any member
        if (!$this->isSuperAdmin) {
            $tenantId = $this->getTenantId();
            $query->where('tenant_id', $tenantId);
        }

        $member = $query->findOrFail($id);
        return $member;
    }

    /**
     * Update a team member
     */
    public function updateMember(int $id, array $data): TenantUser
    {
        $member = $this->getMember($id);

        // Prevent updating owner
        if ($member->isOwner() && !$this->isSuperAdmin) {
            throw ValidationException::withMessages([
                'role' => ['Cannot update the owner. Owner permissions are managed separately.']
            ]);
        }

        $member->update([
            'department' => $data['department'] ?? $member->department,
            'position' => $data['position'] ?? $member->position,
            'availability_status' => $data['availability_status'] ?? $member->availability_status,
            'max_concurrent_chats' => $data['max_concurrent_chats'] ?? $member->max_concurrent_chats,
            'skills' => $data['skills'] ?? $member->skills,
        ]);

        Log::info('Team member updated', [
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'user_id' => auth()->id(),
            'is_super_admin' => $this->isSuperAdmin,
        ]);

        return $member->fresh()->load('user');
    }

    /**
     * Remove a team member
     */
    public function removeMember(int $id): bool
    {
        $member = $this->getMember($id);

        // Prevent removing the owner (unless Super Admin)
        if ($member->isOwner() && !$this->isSuperAdmin) {
            throw ValidationException::withMessages([
                'member' => ['Cannot remove the workspace owner.'],
            ]);
        }

        // Check if this is the last owner (safety check)
        $ownerCount = TenantUser::query()
            ->forTenant($this->getTenantId())
            ->where('role', 'owner')
            ->count();

        if ($ownerCount <= 1 && $member->isOwner()) {
            throw ValidationException::withMessages([
                'member' => ['Cannot remove the last owner of the workspace.'],
            ]);
        }

        // Soft delete the membership
        $member->delete();

        Log::info('Team member removed', [
            'tenant_id' => $this->getTenantId(),
            'member_id' => $member->id,
            'user_id' => auth()->id(),
            'removed_user_id' => $member->user_id,
        ]);

        return true;
    }

    /**
     * Get member statistics
     */
    public function getStatistics(): array
    {
        $query = TenantUser::query();

        // Super Admin sees all tenants
        if ($this->isSuperAdmin) {
            Log::info('Super Admin viewing all team statistics', ['user_id' => $this->user->id]);

            return [
                'total' => $query->count(),
                'online' => (clone $query)->where('availability_status', 'online')->count(),
                'away' => (clone $query)->where('availability_status', 'away')->count(),
                'offline' => (clone $query)->where('availability_status', 'offline')->count(),
                'busy' => (clone $query)->where('availability_status', 'busy')->count(),
                'by_role' => (clone $query)
                    ->selectRaw('role, count(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->toArray(),
                'by_department' => (clone $query)
                    ->whereNotNull('department')
                    ->selectRaw('department, count(*) as count')
                    ->groupBy('department')
                    ->pluck('count', 'department')
                    ->toArray(),
                'by_tenant' => (clone $query)
                    ->selectRaw('tenant_id, count(*) as count')
                    ->groupBy('tenant_id')
                    ->with('tenant')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'tenant_id' => $item->tenant_id,
                            'tenant_name' => $item->tenant?->name ?? 'Unknown',
                            'count' => $item->count,
                        ];
                    })
                    ->toArray(),
                'is_super_admin' => true,
            ];
        }


        $tenantId = $this->getTenantId();
        $query->where('tenant_id', $tenantId);

        // $query = TenantUser::query()->forTenant($this->getTenantId());

        // dd($query->count());

        return [
            'total' => $query->count(),
            'online' => (clone $query)->where('availability_status', 'online')->count(),
            'away' => (clone $query)->where('availability_status', 'away')->count(),
            'offline' => (clone $query)->where('availability_status', 'offline')->count(),
            'busy' => (clone $query)->where('availability_status', 'busy')->count(),
            'by_role' => (clone $query)
                ->selectRaw('role, count(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray(),
            'by_department' => (clone $query)
                ->whereNotNull('department')
                ->selectRaw('department, count(*) as count')
                ->groupBy('department')
                ->pluck('count', 'department')
                ->toArray(),
        ];
    }

    /**
     * Get all departments for filtering
     */
    public function getDepartments(): array
    {

        $query = TenantUser::query();

        // Super Admin sees all departments
        if ($this->isSuperAdmin) {
            Log::info('Super Admin viewing all departments', ['user_id' => $this->user->id]);
            return $query->whereNotNull('department')
                ->select('department')
                ->distinct()
                ->pluck('department')
                ->toArray();
        }

        $tenantId = $this->getTenantId();

        return $query->where('tenant_id', $tenantId)
            ->whereNotNull('department')
            ->select('department')
            ->distinct()
            ->pluck('department')
            ->toArray();
    }

    public function getTenants(): array
    {
        if (!$this->isSuperAdmin) {
            return [];
        }

        return Tenant::active()
            ->select('id', 'name', 'slug', 'status')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

}
