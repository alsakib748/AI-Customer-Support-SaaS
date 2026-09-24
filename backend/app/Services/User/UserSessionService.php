<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserSessionService
{
    public function __construct(
        protected AuditLogService $auditLog,
    ) {
    }

    /**
     * Revoke all of a user's sessions (every device/issued JWT).
     *
     * JWT is stateless here, so we persist a `sessions_revoked_at` timestamp in
     * the user's preferences and reject any token issued before it (see
     * JWTAuthMiddleware + AuthService::refresh). Works without a schema change.
     */
    public function revokeAll(User $actor, User $user, ?string $reason = null): User
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot revoke your own sessions.',
            ]);
        }

        $this->markRevoked($user);

        $this->auditLog->log(
            'user.sessions_revoked',
            'user',
            $user->id,
            null,
            ['email' => $user->email, 'name' => $user->full_name],
            ['actor_id' => $actor->id, 'reason' => $reason]
        );

        return $user->fresh();
    }

    /**
     * Stamp the revocation timestamp onto the user's preferences.
     */
    public function markRevoked(User $user): void
    {
        DB::connection('central')->transaction(function () use ($user) {
            $user->setPreference('sessions_revoked_at', now()->toISOString());
            $user->save();
        });
    }
}