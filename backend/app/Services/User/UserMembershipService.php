<?php

namespace App\Services\User;

use App\Models\TenantUser;
use App\Models\User;

class UserMembershipService
{
    /**
     * All tenant memberships for a user. Answers "which tenants does this user
     * belong to, and with which role?" without touching tenant databases.
     */
    public function memberships(User $user): array
    {
        $memberships = $user->tenantUsers()
            ->with('tenant')
            ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->get();

        return [
            'total' => $memberships->count(),
            'by_role' => $memberships->groupBy('role')->map->count()->toArray(),
            'current_tenant_id' => $user->current_tenant_id,
            'items' => $memberships->map(fn (TenantUser $m) => [
                'tenant_id'   => $m->tenant_id,
                'tenant_name' => $m->tenant?->name,
                'tenant_slug' => $m->tenant?->slug,
                'tenant_status'       => $m->tenant?->status,
                'tenant_status_label' => $m->tenant?->status_label,
                'tenant_status_color' => $m->tenant?->status_color,
                'role'        => $m->role,
                'role_label'  => $m->role_label,
                'is_owner'    => $m->is_owner,
                'department'  => $m->department,
                'position'    => $m->position,
                'joined_at'   => $m->accepted_at?->toISOString() ?? $m->created_at?->toISOString(),
                'invited_at'  => $m->invited_at?->toISOString(),
                'is_current'  => $m->tenant_id === $user->current_tenant_id,
            ])->values(),
        ];
    }
}