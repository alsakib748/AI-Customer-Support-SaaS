<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Default password used when a Super Admin creates a platform user.
     * The account is flagged `must_change_password` so the flag is visible and
     * can be enforced during the authentication hardening phase.
     */
    public const DEFAULT_PASSWORD = '11111111';

    public function __construct(
        protected UserLifecycleService $lifecycle,
        protected UserSessionService $sessions,
    ) {
    }

    public function find(int $id, array $with = []): User
    {
        return User::with($with)->findOrFail($id);
    }

    public function create(User $actor, array $data): User
    {
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'username'   => $data['username'] ?? null,
            'email'      => $data['email'],
            'password'   => Hash::make(self::DEFAULT_PASSWORD),
            'phone'      => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'timezone'   => $data['timezone'] ?? 'UTC',
            'language'   => $data['language'] ?? 'en',
            'is_active'  => true,
            'preferences' => [
                'must_change_password' => true,
            ],
            'uuid'       => (string) Str::uuid(),
        ]);

        $this->lifecycle->audit($actor, 'user.created', $user, [
            'method'             => 'admin',
            'default_password'   => true,
            'must_change_password' => true,
        ], null, ['email' => $user->email, 'name' => $user->full_name]);

        return $user;
    }

    public function update(User $actor, User $user, array $data): User
    {
        $oldEmail = $user->email;
        $emailChanged = isset($data['email']) && $data['email'] !== $oldEmail;

        $user->update([
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name'  => $data['last_name'] ?? $user->last_name,
            'username'   => array_key_exists('username', $data) ? $data['username'] : $user->username,
            'email'      => $data['email'] ?? $user->email,
            'phone'      => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'timezone'   => $data['timezone'] ?? $user->timezone,
            'language'   => $data['language'] ?? $user->language,
        ]);

        if ($emailChanged) {
            // Email is authentication-critical: force re-verification, revoke
            // existing sessions and record a security audit event.
            $user->update(['email_verified_at' => null]);
            $this->sessions->markRevoked($user);
            $this->lifecycle->audit($actor, 'user.email_changed', $user, null, [
                'old_email' => $oldEmail,
                'new_email' => $user->email,
            ]);
        }

        $this->lifecycle->audit($actor, 'user.updated', $user, null, null, [
            'email' => $user->email,
            'name'  => $user->full_name,
        ]);

        return $user->fresh();
    }
}