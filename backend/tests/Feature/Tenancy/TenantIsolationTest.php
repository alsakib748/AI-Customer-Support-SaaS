<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Proves the tenant harness itself: the real tenant migrations run against an
 * isolated database, an explicit tenant context can be established, and two
 * tenants are genuinely isolated from one another.
 */
class TenantIsolationTest extends TestCase
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

    public function test_tenant_migrations_build_the_expected_schema(): void
    {
        $this->tenantSchemaTemplate();

        foreach (['customers', 'conversations', 'messages', 'tickets', 'knowledge_base_articles', 'ai_usage', 'quota_reservations'] as $table) {
            $this->assertTrue(
                Schema::connection('tenant')->hasTable($table),
                "tenant migration must create the [{$table}] table"
            );
        }
    }

    public function test_owner_can_reach_a_permission_protected_tenant_endpoint(): void
    {
        $tenant = $this->newTenant();
        $owner = $this->newTenantUser($tenant, 'owner');
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $owner);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson('/api/v1/customers')->assertOk();
    }

    public function test_member_without_the_permission_is_denied(): void
    {
        $tenant = $this->newTenant();
        $outsider = $this->newTenantUser($tenant, 'ghost_role');
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $outsider);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        // 'ghost_role' is not a seeded role, so it holds no tenant permissions.
        $this->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_tenant_a_cannot_see_tenant_b_customers(): void
    {
        $tenantA = $this->newTenant(['name' => 'Tenant A']);
        $ownerA = $this->newTenantUser($tenantA, 'owner');
        $this->newSubscription($tenantA);

        $tenantB = $this->newTenant(['name' => 'Tenant B']);
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);

        $this->establishTenantContext($tenantA);
        DB::connection('tenant')->table('customers')->insert([
            'first_name' => 'Alice-A', 'email' => 'alice@a.test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->establishTenantContext($tenantB);
        DB::connection('tenant')->table('customers')->insert([
            'first_name' => 'Bob-B', 'email' => 'bob@b.test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->establishTenantContext($tenantA, $ownerA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $response = $this->getJson('/api/v1/customers');
        $response->assertOk();
        $response->assertSee('Alice-A');
        $response->assertDontSee('Bob-B');
    }

    public function test_cross_tenant_membership_is_rejected(): void
    {
        $tenantA = $this->newTenant();
        $ownerA = $this->newTenantUser($tenantA, 'owner');
        $this->newSubscription($tenantA);

        $tenantB = $this->newTenant();
        $this->newSubscription($tenantB);

        $this->establishTenantContext($tenantA, $ownerA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        // Force the request into tenant B while authenticated as A's owner.
        app()->instance('current_tenant', $tenantB);
        request()->attributes->set('current_tenant', $tenantB);

        // SetPermissionTeam verifies membership before granting the tenant scope.
        $this->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_tenant_and_central_connections_are_distinct(): void
    {
        $tenant = $this->newTenant();
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant);

        $this->assertTrue(Schema::connection('tenant')->hasTable('customers'));
        $this->assertTrue(Schema::connection('central')->hasTable('tenants'));
        $this->assertFalse(Schema::connection('central')->hasTable('customers'));
    }
}
