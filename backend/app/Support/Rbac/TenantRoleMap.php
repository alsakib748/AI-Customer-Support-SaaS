<?php

namespace App\Support\Rbac;

/**
 * Maps the tenant-facing role vocabulary (tenant_user / invitations) onto the
 * canonical Spatie role names seeded by RbacSeeder.
 *
 * The two vocabularies diverged historically: tenant pivots and invitations use
 * "agent"/"viewer", while the permission matrix is attached to "support_agent"
 * (and a read-only "viewer"). Every path that creates or syncs a Spatie role
 * from a tenant role MUST go through this map, otherwise the member ends up
 * with a permission-less role and an empty sidebar.
 */
class TenantRoleMap
{
    /**
     * tenant role => canonical Spatie role.
     *
     * @var array<string, string>
     */
    protected const MAP = [
        'owner' => 'owner',
        'admin' => 'admin',
        'manager' => 'manager',
        'agent' => 'support_agent',
        'support_agent' => 'support_agent',
        'viewer' => 'viewer',
        'super_admin' => 'super_admin',
    ];

    /**
     * Roles a tenant owner/manager may assign to a member (tenant vocabulary).
     *
     * @var string[]
     */
    public const ASSIGNABLE = ['admin', 'manager', 'agent', 'viewer'];

    /**
     * Resolve a tenant role name to its canonical Spatie role name.
     */
    public static function toSpatie(?string $tenantRole): ?string
    {
        if ($tenantRole === null || $tenantRole === '') {
            return null;
        }

        return self::MAP[strtolower(trim($tenantRole))] ?? null;
    }

    /**
     * Resolve a tenant role, falling back to the operational default so a
     * legacy/unexpected value can never resolve to a permission-less role.
     */
    public static function toSpatieOrSupportAgent(?string $tenantRole): string
    {
        return self::toSpatie($tenantRole) ?? 'support_agent';
    }

    /**
     * Every canonical Spatie role name.
     *
     * @return string[]
     */
    public static function canonicalRoles(): array
    {
        return array_values(array_unique(array_values(self::MAP)));
    }
}
