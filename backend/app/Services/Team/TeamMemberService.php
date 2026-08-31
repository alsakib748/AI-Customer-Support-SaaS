<?php
// app/Services/Team/TeamMemberService.php

namespace App\Services\Team;

use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TeamMemberService
{
    protected $tenant;

    public function __construct()
    {
        $this->tenant = app('current_tenant');
    }

    /**
     * Get paginated team members
     */
    public function getMembers(array $filters = [])
    {
        $query = TenantUser::query()
            ->forTenant($this->tenant->id)
            ->with('user');

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
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

        // Sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        // Whitelist allowed sort fields
        $allowedSorts = ['name', 'email', 'department', 'position', 'availability_status', 'created_at', 'role'];

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

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get a single team member
     */
    public function getMember(int $id): TenantUser
    {
        $member = TenantUser::query()
            ->forTenant($this->tenant->id)
            ->with('user')
            ->findOrFail($id);

        return $member;
    }

    /**
     * Update a team member
     */
    public function updateMember(int $id, array $data): TenantUser
    {
        $member = $this->getMember($id);

        // Don't allow updating owner's role/permissions here
        if ($member->isOwner()) {
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
            'tenant_id' => $this->tenant->id,
            'member_id' => $member->id,
            'user_id' => auth()->id(),
            'changes' => $data,
        ]);

        return $member->fresh()->load('user');
    }

    /**
     * Remove a team member
     */
    public function removeMember(int $id): bool
    {
        $member = $this->getMember($id);

        // Prevent removing the owner
        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'member' => ['Cannot remove the workspace owner.'],
            ]);
        }

        // Check if this is the last owner (should never happen, but safety check)
        $ownerCount = TenantUser::query()
            ->forTenant($this->tenant->id)
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
            'tenant_id' => $this->tenant->id,
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
        $query = TenantUser::query()->forTenant($this->tenant->id);

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
        return TenantUser::query()
            ->forTenant($this->tenant->id)
            ->whereNotNull('department')
            ->select('department')
            ->distinct()
            ->pluck('department')
            ->toArray();
    }

    /**
     * Check if user has permission to manage team
     */
    public function canManageTeam(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $tenantUser = $user->currentTenantUser;

        if (!$tenantUser) {
            return false;
        }

        return $tenantUser->isAdmin() || $tenantUser->isOwner();
    }
}