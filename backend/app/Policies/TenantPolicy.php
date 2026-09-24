<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user): bool
    {
        return $user->hasAnyPermission(['platform.tenants', 'platform.tenants.view']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.update');
    }

    public function activate(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.activate');
    }

    public function suspend(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.suspend');
    }

    public function archive(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.archive');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.restore');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.manage');
    }

    public function transferOwner(User $user): bool
    {
        return $user->hasPermissionTo('platform.tenants.transfer_owner');
    }

    /**
     * Deletion is intentionally not permitted — tenants are archived, not deleted.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}