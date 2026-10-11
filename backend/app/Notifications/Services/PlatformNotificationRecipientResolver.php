<?php

namespace App\Notifications\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves which authorized Super Admins should receive a platform event.
 * Recipients are always resolved server-side from the central user + role
 * system — never from a client-supplied id or role.
 */
class PlatformNotificationRecipientResolver
{
    /**
     * Active Super Admins on the platform scope.
     *
     * @return Collection<int, User>
     */
    public function superAdmins(): Collection
    {
        return User::query()
            ->role('super_admin')
            ->where('is_active', true)
            ->get();
    }
}
