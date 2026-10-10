<?php

namespace App\Policies;

use App\Models\User;

/**
 * Defense-in-depth authorization for platform settings. Route middleware
 * already enforces the granular permissions; this policy guarantees the same
 * rule even if a route is wired incorrectly.
 */
class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->grants($user);
    }

    public function view(User $user): bool
    {
        return $this->grants($user);
    }

    public function update(User $user): bool
    {
        return $this->grants($user);
    }

    protected function grants(User $user): bool
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        foreach (['platform.settings', 'platform.settings.view'] as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue; // permission not seeded yet
            }
        }

        return false;
    }
}
