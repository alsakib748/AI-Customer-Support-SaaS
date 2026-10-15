<?php

// app/Services/Team/InvitationService.php

namespace App\Services\Team;

use App\Mail\TeamInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Rbac\TenantRoleMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InvitationService
{
    protected const EXPIRY_DAYS = 7;

    protected ?Tenant $tenant = null;

    protected ?User $user;

    public function __construct(Request $request)
    {
        $this->user = auth()->user();

        $tenant = $request->attributes->get('current_tenant')
            ?? app('current_tenant')
            ?? ($this->user?->currentTenant);

        if (! $tenant) {
            $tenantId = $request->header('X-Tenant-Id')
                ?? $request->header('X-Tenant-ID')
                ?? $request->header('x-tenant-id');

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
            }
        }

        if (! $tenant && $this->user) {
            $tenant = $this->user->tenants()->first();
        }

        $this->tenant = $tenant;
    }

    /**
     * Resolve the current tenant, failing when the request is not tenant-scoped.
     * Public routes (e.g. accepting an invitation) have no tenant context and
     * must not depend on it, so resolution is deferred to the tenant-scoped
     * methods instead of the constructor.
     */
    protected function tenantOrFail(): Tenant
    {
        if (! $this->tenant) {
            throw new \RuntimeException('No tenant found in current context');
        }

        return $this->tenant;
    }

    /**
     * Check if user can manage invitations
     */
    public function canManageInvitations(): bool
    {
        if (! $this->user) {
            return false;
        }

        if ($this->user->hasRole('super_admin')) {
            return true;
        }

        return $this->user->hasPermissionTo('team.invite');
    }

    /**
     * Create a new invitation
     */
    public function createInvitation(array $data): TenantInvitation
    {
        // Check if user already exists in tenant
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            $exists = TenantUser::query()
                ->forTenant($this->tenantOrFail()->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member of the workspace.'],
                ]);
            }
        }

        // Check for pending invitation
        $pending = TenantInvitation::query()
            ->where('tenant_id', $this->tenantOrFail()->id)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'email' => ['An invitation has already been sent to this email.'],
            ]);
        }

        // Create invitation. Only a hash of the token is persisted; the
        // plaintext token is exposed to the caller once so it can be emailed.
        $plainToken = Str::random(64);

        $invitation = TenantInvitation::create([
            'tenant_id' => $this->tenantOrFail()->id,
            'invited_by' => auth()->id(),
            'email' => $data['email'],
            'role' => $data['role'] ?? 'agent',
            'department' => $data['department'] ?? null,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'metadata' => [
                'position' => $data['position'] ?? null,
                'invited_at' => now()->toISOString(),
            ],
        ]);

        $invitation->setAttribute('plain_token', $plainToken);

        $this->sendInvitationEmail($invitation, $plainToken);

        Log::info('Team invitation created', [
            'tenant_id' => $this->tenantOrFail()->id,
            'email' => $data['email'],
            'invited_by' => auth()->id(),
        ]);

        return $invitation;
    }

    /**
     * Get all invitations
     */
    public function getInvitations(array $filters = [])
    {
        $query = TenantInvitation::query()
            ->where('tenant_id', $this->tenantOrFail()->id);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now());
            } elseif ($filters['status'] === 'accepted') {
                $query->whereNotNull('accepted_at');
            } elseif ($filters['status'] === 'expired') {
                $query->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '<=', now());
            } elseif ($filters['status'] === 'revoked') {
                $query->whereNotNull('revoked_at');
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100));
    }

    /**
     * Accept an invitation
     */
    public function acceptInvitation(string $token, array $data): TenantUser
    {
        $invitation = TenantInvitation::query()
            ->where(function ($query) use ($token) {
                // New invitations store a SHA-256 hash; legacy rows stored the
                // raw token, so accept either form.
                $query->where('token', hash('sha256', $token))
                    ->orWhere('token', $token);
            })
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Check if email matches
        if ($invitation->email !== $data['email']) {
            throw ValidationException::withMessages([
                'email' => ['This invitation is for a different email address.'],
            ]);
        }

        // Find or create user
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            $user = User::create([
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? Str::random(16)),
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]);
        }

        // Check if already a member
        $exists = TenantUser::query()
            ->forTenant($invitation->tenant_id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member.'],
            ]);
        }

        // Create membership
        $tenantUser = TenantUser::create([
            'tenant_id' => $invitation->tenant_id,
            'user_id' => $user->id,
            'role' => $invitation->role,
            'department' => $invitation->department,
            'position' => $invitation->metadata['position'] ?? null,
            'availability_status' => 'online',
            'max_concurrent_chats' => 5,
            'skills' => [],
            'accepted_at' => now(),
        ]);

        // Assign the Spatie role inside the invitation's tenant team. Accept is
        // a public route, so the team context is not set for us.
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($invitation->tenant_id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleName = TenantRoleMap::toSpatie($invitation->role);
        if ($roleName === null) {
            Log::warning('Invitation accepted with an unmapped role; defaulting to support_agent', [
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
            ]);
            $roleName = 'support_agent';
        }

        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'api',
        ]);
        $user->assignRole($role);

        setPermissionsTeamId($previousTeamId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Mark invitation as accepted
        $invitation->update([
            'accepted_at' => now(),
            'metadata' => array_merge($invitation->metadata ?? [], [
                'accepted_ip' => request()->ip(),
                'accepted_at' => now()->toISOString(),
            ]),
        ]);

        Log::info('Team invitation accepted', [
            'tenant_id' => $invitation->tenant_id,
            'email' => $data['email'],
            'user_id' => $user->id,
        ]);

        return $tenantUser->load('user');
    }

    /**
     * Resend an invitation
     */
    public function resendInvitation(int $id): TenantInvitation
    {
        $invitation = TenantInvitation::query()
            ->where('tenant_id', $this->tenantOrFail()->id)
            ->findOrFail($id);

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has already been accepted.'],
            ]);
        }

        if ($invitation->revoked_at) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has been revoked.'],
            ]);
        }

        $plainToken = Str::random(64);

        $invitation->update([
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'token' => hash('sha256', $plainToken),
            'metadata' => array_merge($invitation->metadata ?? [], [
                'resent_at' => now()->toISOString(),
                'resent_by' => auth()->id(),
            ]),
        ]);

        $invitation->setAttribute('plain_token', $plainToken);

        $this->sendInvitationEmail($invitation, $plainToken);

        Log::info('Team invitation resent', [
            'tenant_id' => $this->tenantOrFail()->id,
            'invitation_id' => $id,
            'resent_by' => auth()->id(),
        ]);

        return $invitation;
    }

    /**
     * Email the invitation link to the invited user.
     */
    protected function sendInvitationEmail(TenantInvitation $invitation, string $plainToken): void
    {
        try {
            $inviter = $invitation->invited_by ? User::find($invitation->invited_by) : null;

            Mail::to($invitation->email)->queue(new TeamInvitationMail(
                acceptUrl: $this->buildAcceptUrl($plainToken),
                workspaceName: $this->tenantOrFail()->name,
                role: $invitation->role,
                inviterName: $inviter?->full_name,
                expiresAt: $invitation->expires_at?->toDateTimeString(),
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send team invitation email', [
                'tenant_id' => $this->tenantOrFail()->id,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the public URL a user follows to accept an invitation.
     */
    protected function buildAcceptUrl(string $plainToken): string
    {
        $base = config('app.frontend_url') ?: config('app.url');

        return rtrim((string) $base, '/').'/team/invitations/accept/'.$plainToken;
    }

    /**
     * Revoke an invitation
     */
    public function revokeInvitation(int $id): bool
    {
        $invitation = TenantInvitation::query()
            ->where('tenant_id', $this->tenantOrFail()->id)
            ->findOrFail($id);

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'invitation' => ['Cannot revoke an already accepted invitation.'],
            ]);
        }

        $invitation->update([
            'revoked_at' => now(),
            'metadata' => array_merge($invitation->metadata ?? [], [
                'revoked_at' => now()->toISOString(),
                'revoked_by' => auth()->id(),
            ]),
        ]);

        Log::info('Team invitation revoked', [
            'tenant_id' => $this->tenantOrFail()->id,
            'invitation_id' => $id,
            'revoked_by' => auth()->id(),
        ]);

        return true;
    }
}
