<?php

namespace Tests\Feature\Ops;

use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Phase 6 WS3 regression: route-level `permission:` middleware closes the RBAC
 * gaps left by the commented-out controller checks.
 */
class RoutePermissionEnforcementTest extends TestCase
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

    private function boot(string $role): void
    {
        $tenant = $this->newTenant();
        $user = $this->newTenantUser($tenant, $role);
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $user);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());
    }

    public function test_support_agent_cannot_update_the_workspace(): void
    {
        $this->boot('support_agent');

        $this->putJson('/api/v1/workspace', ['name' => 'Hacked'])->assertForbidden();
    }

    public function test_manager_cannot_manage_the_subscription(): void
    {
        $this->boot('manager');

        $this->postJson('/api/v1/subscription/cancel', ['reason' => 'x'])->assertForbidden();
    }

    public function test_support_agent_cannot_delete_knowledge_base_articles(): void
    {
        $this->boot('support_agent');

        $this->deleteJson('/api/v1/knowledge-base/articles/1')->assertForbidden();
    }

    public function test_owner_can_reach_the_workspace(): void
    {
        $this->boot('owner');

        $this->getJson('/api/v1/workspace')->assertSuccessful();
    }
}
