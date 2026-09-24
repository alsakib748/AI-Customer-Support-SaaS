<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserLifecycleService
{
    public function __construct(
        protected AuditLogService $auditLog,
        protected UserSessionService $sessions,
    ) {
    }

    /**
     * Suspend a user globally — they can no longer authenticate anywhere.
     * Suspension is tracked on existing columns + preferences (no schema change).
     */
    public function suspend(User $actor, User $user, ?string $reason = null): User
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot suspend your own account.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'status' => 'This account is already suspended.',
            ]);
        }

        // Never lock the platform out: at least one active Super Admin must remain.
        if ($user->isSuperAdmin()) {
            $otherActive = User::role('super_admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherActive === 0) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot suspend the last active Super Admin.',
                ]);
            }
        }

        DB::connection('central')->transaction(function () use ($user, $reason) {
            $user->update(['is_active' => false]);

            $user->setPreference('suspended_at', now()->toISOString());
            if ($reason) {
                $user->setPreference('suspension_reason', $reason);
            }
            $user->save();
        });

        $this->audit($actor, 'user.suspended', $user, ['reason' => $reason]);

        return $user->fresh();
    }

    /**
     * Re-activate a suspended account. Old sessions are not restored — the user
     * must authenticate again (a fresh login is already required while suspended).
     */
    public function activate(User $user): User
    {
        if ($user->is_active) {
            throw ValidationException::withMessages([
                'status' => 'This account is already active.',
            ]);
        }

        DB::connection('central')->transaction(function () use ($user) {
            $user->update(['is_active' => true]);
            $user->setPreference('suspended_at', null);
            $user->setPreference('suspension_reason', null);
            $user->save();
        });

        $this->audit(request()->user(), 'user.activated', $user);

        return $user->fresh();
    }

    /**
     * Single audit helper so all lifecycle events are recorded consistently.
     */
    public function audit(?User $actor, string $action, User $user, ?array $metadata = null, ?array $newValues = null, ?array $oldValues = null): void
    {
        $this->auditLog->log(
            $action,
            'user',
            $user->id,
            $oldValues,
            $newValues ?? ['email' => $user->email, 'name' => $user->full_name],
            array_merge(['actor_id' => $actor?->id], $metadata ?? [])
        );
    }
}