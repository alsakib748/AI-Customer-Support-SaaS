<?php

namespace App\Policies;

use App\Models\PlatformIncident;
use App\Models\User;

/**
 * Defense-in-depth authorization for incidents. Route middleware enforces the
 * granular permissions; this policy guarantees the same rules even if a route
 * is wired incorrectly.
 */
class PlatformIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->grants($user, ['platform.system_health', 'platform.system_health.view']);
    }

    public function view(User $user, PlatformIncident $incident): bool
    {
        return $this->grants($user, ['platform.system_health', 'platform.system_health.view']);
    }

    public function acknowledge(User $user, PlatformIncident $incident): bool
    {
        return $this->grants($user, ['platform.system_health', 'platform.system_health.manage']);
    }

    public function resolve(User $user, PlatformIncident $incident): bool
    {
        return $this->grants($user, ['platform.system_health', 'platform.system_health.manage']);
    }

    protected function grants(User $user, array $permissions): bool
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (\Throwable) {
                // permission not seeded yet
            }
        }

        return false;
    }
}