<?php
// app/Services/Team/InvitationService.php

namespace App\Services\Team;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class InvitationService
{
    protected Tenant $tenant;
    protected ?User $user;

    public function __construct(Request $request)
    {
        $this->user = auth()->user();

        $tenant = $request->attributes->get('current_tenant')
            ?? app('current_tenant')
            ?? ($this->user?->currentTenant);

        if (!$tenant) {
            $tenantId = $request->header('X-Tenant-Id')
                ?? $request->header('X-Tenant-ID')
                ?? $request->header('x-tenant-id');

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
            }
        }

        if (!$tenant && $this->user) {
            $tenant = $this->user->tenants()->first();
        }

        if (!$tenant) {
            throw new \RuntimeException('No tenant found in current context');
        }

        $this->tenant = $tenant;
    }

    /**
     * Check if user can manage invitations
     */
    public function canManageInvitations(): bool
    {
        if (!$this->user) {
            return false;
        }

        if ($this->user->hasRole('super-admin')) {
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
                ->forTenant($this->tenant->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member of the workspace.']
                ]);
            }
        }

        // Check for pending invitation
        $pending = TenantInvitation::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'email' => ['An invitation has already been sent to this email.']
            ]);
        }

        // Create invitation
        $invitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'invited_by' => auth()->id(),
            'email' => $data['email'],
            'role' => $data['role'] ?? 'agent',
            'department' => $data['department'] ?? null,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'metadata' => [
                'position' => $data['position'] ?? null,
                'invited_at' => now()->toISOString(),
            ],
        ]);

        Log::info('Team invitation created', [
            'tenant_id' => $this->tenant->id,
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
            ->where('tenant_id', $this->tenant->id);

        if (!empty($filters['search'])) {
            $query->where('email', 'ILIKE', "%{$filters['search']}%");
        }

        if (!empty($filters['status'])) {
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
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Accept an invitation
     */
    public function acceptInvitation(string $token, array $data): TenantUser
    {
        $invitation = TenantInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Check if email matches
        if ($invitation->email !== $data['email']) {
            throw ValidationException::withMessages([
                'email' => ['This invitation is for a different email address.']
            ]);
        }

        // Find or create user
        $user = User::where('email', $data['email'])->first();

        if (!$user) {
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
                'email' => ['This user is already a member.']
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

        // Assign Spatie role
        $role = Role::firstOrCreate([
            'name' => $invitation->role,
            'guard_name' => 'api',
        ]);
        $user->assignRole($role);

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
            ->where('tenant_id', $this->tenant->id)
            ->findOrFail($id);

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has already been accepted.']
            ]);
        }

        if ($invitation->revoked_at) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has been revoked.']
            ]);
        }

        $invitation->update([
            'expires_at' => now()->addDays(7),
            'token' => Str::random(64),
            'metadata' => array_merge($invitation->metadata ?? [], [
                'resent_at' => now()->toISOString(),
                'resent_by' => auth()->id(),
            ]),
        ]);

        Log::info('Team invitation resent', [
            'tenant_id' => $this->tenant->id,
            'invitation_id' => $id,
            'resent_by' => auth()->id(),
        ]);

        return $invitation->fresh();
    }

    /**
     * Revoke an invitation
     */
    public function revokeInvitation(int $id): bool
    {
        $invitation = TenantInvitation::query()
            ->where('tenant_id', $this->tenant->id)
            ->findOrFail($id);

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'invitation' => ['Cannot revoke an already accepted invitation.']
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
            'tenant_id' => $this->tenant->id,
            'invitation_id' => $id,
            'revoked_by' => auth()->id(),
        ]);

        return true;
    }
}
