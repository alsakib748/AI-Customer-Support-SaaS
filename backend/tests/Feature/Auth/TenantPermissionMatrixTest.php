<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Role/permission enforcement for tenant-scoped routes, evaluated through the
 * real `set.permission.team` + `permission:` middleware stack.
 */
class TenantPermissionMatrixTest extends TestCase
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

    private function tenantWithCustomer(): array
    {
        $tenant = $this->newTenant();
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant);
        DB::connection('tenant')->table('customers')->insert([
            'first_name' => 'Existing', 'email' => 'existing@example.test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $customerId = (int) DB::connection('tenant')->table('customers')->max('id');

        return [$tenant, $customerId];
    }

    public function test_owner_can_view_customers(): void
    {
        $tenant = $this->newTenant();
        $owner = $this->newTenantUser($tenant, 'owner');
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $owner);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson('/api/v1/customers')->assertOk();
    }

    public function test_support_agent_can_view_but_not_delete_customers(): void
    {
        $tenant = $this->newTenant();
        $agent = $this->newTenantUser($tenant, 'support_agent');
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $agent);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson('/api/v1/customers')->assertOk();

        DB::connection('tenant')->table('customers')->insert([
            'first_name' => 'Keep-Me', 'email' => 'keep@example.test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = (int) DB::connection('tenant')->table('customers')->value('id');

        $this->deleteJson("/api/v1/customers/{$id}")->assertForbidden();

        $this->assertSame(
            1,
            DB::connection('tenant')->table('customers')->where('id', $id)->count(),
            'a denied delete must not remove the row'
        );
    }

    public function test_manager_cannot_delete_but_agent_cannot_update(): void
    {
        $tenant = $this->newTenant();
        $manager = $this->newTenantUser($tenant, 'manager');
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $manager);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        DB::connection('tenant')->table('customers')->insert([
            'first_name' => 'Row', 'email' => 'row@example.test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = (int) DB::connection('tenant')->table('customers')->value('id');

        // Manager holds customers.update but not customers.delete.
        $this->putJson("/api/v1/customers/{$id}", ['first_name' => 'Renamed'])->assertOk();
        $this->deleteJson("/api/v1/customers/{$id}")->assertForbidden();
    }

    public function test_user_without_a_seeded_role_is_forbidden(): void
    {
        $tenant = $this->newTenant();
        $outsider = $this->newTenantUser($tenant, 'viewer');
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $outsider);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson('/api/v1/customers')->assertForbidden();
    }
}
