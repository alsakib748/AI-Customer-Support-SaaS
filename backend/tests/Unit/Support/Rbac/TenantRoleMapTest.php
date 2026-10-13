<?php

namespace Tests\Unit\Support\Rbac;

use App\Support\Rbac\TenantRoleMap;
use PHPUnit\Framework\TestCase;

class TenantRoleMapTest extends TestCase
{
    public function test_maps_tenant_agent_role_to_support_agent(): void
    {
        $this->assertSame('support_agent', TenantRoleMap::toSpatie('agent'));
        $this->assertSame('support_agent', TenantRoleMap::toSpatie('AGENT'));
        $this->assertSame('support_agent', TenantRoleMap::toSpatie(' support_agent '));
    }

    public function test_passthrough_for_canonical_roles(): void
    {
        foreach (['owner', 'admin', 'manager', 'viewer', 'super_admin'] as $role) {
            $this->assertSame($role, TenantRoleMap::toSpatie($role));
        }
    }

    public function test_unknown_and_empty_values_map_to_null(): void
    {
        $this->assertNull(TenantRoleMap::toSpatie('wizard'));
        $this->assertNull(TenantRoleMap::toSpatie(''));
        $this->assertNull(TenantRoleMap::toSpatie(null));
    }

    public function test_assignable_vocabulary_is_the_tenant_facing_set(): void
    {
        $this->assertSame(['admin', 'manager', 'agent', 'viewer'], TenantRoleMap::ASSIGNABLE);
    }
}
