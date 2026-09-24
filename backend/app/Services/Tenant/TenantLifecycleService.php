<?php

namespace App\Services\Tenant;

use App\Events\Tenant\TenantActivated;
use App\Events\Tenant\TenantArchived;
use App\Events\Tenant\TenantOwnerTransferred;
use App\Events\Tenant\TenantRestored;
use App\Events\Tenant\TenantSuspended;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class TenantLifecycleService
{
    public function __construct(
        protected AuditLogService $auditLog,
    ) {
    }

    /**
     * Activate a tenant. If provisioning previously failed, re-provisions it.
     */
    public function activate(Tenant $tenant): Tenant
    {
        if ($tenant->status === Tenant::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'tenant' => ['An archived tenant cannot be activated. Restore it first.'],
            ]);
        }

        $tenant->update([
            'status'            => Tenant::STATUS_ACTIVE,
            'suspended_at'      => null,
            'suspension_reason' => null,
            'provisioning_error' => null,
        ]);

        $this->auditLog->log(
            'tenant.activated',
            'tenant',
            $tenant->id,
            null,
            ['name' => $tenant->name, 'slug' => $tenant->slug]
        );

        TenantActivated::dispatch($tenant);

        return $tenant->fresh();
    }

    /**
     * Suspend a tenant. Reversible — nothing is deleted.
     */
    public function suspend(Tenant $tenant, string $reason = null): Tenant
    {
        if ($tenant->status === Tenant::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'tenant' => ['An archived tenant cannot be suspended.'],
            ]);
        }

        $tenant->update([
            'status'              => Tenant::STATUS_SUSPENDED,
            'suspended_at'        => now(),
            'suspension_reason'   => $reason,
        ]);

        $this->auditLog->log(
            'tenant.suspended',
            'tenant',
            $tenant->id,
            null,
            [
                'name'   => $tenant->name,
                'slug'   => $tenant->slug,
                'reason' => $reason,
            ],
            ['reason' => $reason]
        );

        TenantSuspended::dispatch($tenant, $reason);

        return $tenant->fresh();
    }

    /**
     * Archive a tenant — long-term, no normal access, data is retained.
     */
    public function archive(Tenant $tenant, string $confirmation = null): Tenant
    {
        // Require explicit confirmation text "ARCHIVE" for this destructive action.
        if ($confirmation !== 'ARCHIVE') {
            throw ValidationException::withMessages([
                'confirmation' => ['Type "ARCHIVE" to confirm tenant archiving.'],
            ]);
        }

        $tenant->update([
            'status'           => Tenant::STATUS_ARCHIVED,
            'archived_at'      => now(),
            'suspended_at'     => $tenant->suspended_at ?? now(),
        ]);

        $this->auditLog->log(
            'tenant.archived',
            'tenant',
            $tenant->id,
            null,
            ['name' => $tenant->name, 'slug' => $tenant->slug]
        );

        TenantArchived::dispatch($tenant);

        return $tenant->fresh();
    }

    /**
     * Restore an archived (or suspended) tenant.
     */
    public function restore(Tenant $tenant): Tenant
    {
        if ($tenant->status !== Tenant::STATUS_ARCHIVED && $tenant->status !== Tenant::STATUS_SUSPENDED) {
            throw ValidationException::withMessages([
                'tenant' => ['Only suspended or archived tenants can be restored.'],
            ]);
        }

        $tenant->update([
            'status'            => Tenant::STATUS_ACTIVE,
            'archived_at'       => null,
            'suspended_at'      => null,
            'suspension_reason' => null,
        ]);

        $this->auditLog->log(
            'tenant.restored',
            'tenant',
            $tenant->id,
            null,
            ['name' => $tenant->name, 'slug' => $tenant->slug]
        );

        TenantRestored::dispatch($tenant);

        return $tenant->fresh();
    }

    /**
     * Transfer ownership to an existing member of the tenant.
     * The target user MUST already belong to the tenant.
     */
    public function transferOwner(Tenant $tenant, int $userId): Tenant
    {
        $target = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'user_id' => ['The target user must already be a member of this tenant.'],
            ]);
        }

        $oldOwner = TenantUser::where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->first();

        DB::transaction(function () use ($tenant, $target, $oldOwner) {
            if ($oldOwner && $oldOwner->id !== $target->id) {
                // Old owner → admin
                $oldOwner->update(['role' => 'admin']);
            }

            // New owner → owner
            $target->update(['role' => 'owner']);

            // Reconcile Spatie roles
            $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

            $oldUser = $oldOwner ? User::find($oldOwner->user_id) : null;
            $newUser = User::find($target->user_id);

            if ($newUser && ! $newUser->hasRole($ownerRole)) {
                $newUser->assignRole($ownerRole);
            }
            if ($oldUser && $oldUser->id !== $newUser->id && ! $oldUser->hasRole($adminRole)) {
                $oldUser->assignRole($adminRole);
            }
        });

        $this->auditLog->log(
            'tenant.owner_transferred',
            'tenant',
            $tenant->id,
            null,
            [
                'name'               => $tenant->name,
                'new_owner_user_id'  => $userId,
                'new_owner_email'    => $target->user?->email,
            ]
        );

        TenantOwnerTransferred::dispatch($tenant, User::find($userId));

        return $tenant->fresh();
    }
}