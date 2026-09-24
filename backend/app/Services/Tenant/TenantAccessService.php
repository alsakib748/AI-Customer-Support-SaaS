<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Services\AuditLogService;
use Illuminate\Validation\ValidationException;

class TenantAccessService
{
    public function __construct(
        protected AuditLogService $auditLog,
        protected TenantLifecycleService $lifecycle,
    ) {
    }

    /**
     * Establish a temporary "Manage Tenant" context for a Super Admin.
     * The Super Admin's current_tenant_id is set so the tenant application
     * resolves correctly, but NO permanent tenant_user membership is created.
     */
    public function enterContext(Tenant $tenant): Tenant
    {
        if ($tenant->is_archived) {
            throw ValidationException::withMessages([
                'tenant' => ['This tenant is archived and cannot be managed.'],
            ]);
        }

        $user = auth()->user();

        if ($user) {
            $user->update(['current_tenant_id' => $tenant->id]);
        }

        $this->auditLog->log(
            'tenant.context_entered',
            'tenant',
            $tenant->id,
            null,
            ['name' => $tenant->name, 'slug' => $tenant->slug]
        );

        return $tenant->fresh();
    }

    /**
     * Exit the temporary tenant context and return to the platform scope.
     */
    public function exitContext(): void
    {
        $user = auth()->user();

        if ($user) {
            $this->auditLog->log(
                'tenant.context_exited',
                'admin',
                $user->id,
                null,
                ['previous_tenant_id' => $user->current_tenant_id]
            );
            $user->update(['current_tenant_id' => null]);
        }
    }
}