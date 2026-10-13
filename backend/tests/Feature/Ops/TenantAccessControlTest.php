<?php

namespace Tests\Feature\Ops;

use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Phase 6 WS3 regression: tenant-scoped management endpoints must reject a
 * caller who is not a member (or super admin) of the tenant addressed in the
 * URL, even when the caller is an authenticated owner of another tenant.
 */
class TenantAccessControlTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCentralSchema();
        $this->useIsolatedTenantDatabase();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    public function test_an_owner_cannot_update_another_tenant(): void
    {
        $tenantA = $this->newTenant();
        $ownerA = $this->newTenantUser($tenantA, 'owner');
        $this->newSubscription($tenantA);

        $tenantB = $this->newTenant();
        $originalName = $tenantB->name;

        $this->establishTenantContext($tenantA, $ownerA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->putJson("/api/v1/tenants/{$tenantB->id}", ['name' => 'Hijacked Workspace'])
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantB->id,
            'name' => $originalName,
        ], 'central');
    }

    public function test_owner_cannot_list_users_of_another_tenant(): void
    {
        $tenantA = $this->newTenant();
        $ownerA = $this->newTenantUser($tenantA, 'owner');
        $this->newSubscription($tenantA);

        $tenantB = $this->newTenant();
        $this->newTenantUser($tenantB, 'owner');

        $this->establishTenantContext($tenantA, $ownerA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson("/api/v1/tenants/{$tenantB->id}/users")->assertForbidden();
    }

    public function test_owner_can_still_list_users_of_their_own_tenant(): void
    {
        $tenantA = $this->newTenant();
        $ownerA = $this->newTenantUser($tenantA, 'owner');
        $this->newSubscription($tenantA);

        $this->establishTenantContext($tenantA, $ownerA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson("/api/v1/tenants/{$tenantA->id}/users")->assertOk();
    }
}
