<?php

namespace Tests\Feature\Analytics;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAnalyticsDomain;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Route-level RBAC on the analytics endpoints.
 *
 * Only `overview`, `conversations` and `customers` carry `permission:`
 * middleware; `agents`, `tickets`, `ai`, `widget` and `knowledge-base` are
 * reachable by any authenticated tenant member with an active subscription
 * (the in-controller `hasPermissionTo` guards are commented out).
 */
class AnalyticsRbacTest extends TestCase
{
    use InteractsWithAnalyticsDomain;
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedTenantDatabase();

        config(['database.default' => 'central']);
        DB::purge('central');

        $this->createCentralSchema();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();

        parent::tearDown();
    }

    public function test_overview_requires_the_view_permission(): void
    {
        $this->bootAnalyticsWorkspace('no_permissions');

        $this->getJson('/api/v1/analytics/overview')
            ->assertStatus(403)
            ->assertJsonPath('permission', 'analytics.view');
    }

    public function test_conversations_requires_the_conversations_permission(): void
    {
        $this->bootAnalyticsWorkspace('no_permissions');

        $this->getJson('/api/v1/analytics/conversations')
            ->assertStatus(403)
            ->assertJsonPath('permission', 'analytics.conversations');
    }

    public function test_customers_requires_the_customers_permission(): void
    {
        $this->bootAnalyticsWorkspace('no_permissions');

        $this->getJson('/api/v1/analytics/customers')
            ->assertStatus(403)
            ->assertJsonPath('permission', 'analytics.customers');
    }
}
