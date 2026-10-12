<?php

namespace Tests\Feature\Analytics;

use App\Jobs\Analytics\GenerateAnalyticsExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithAnalyticsDomain;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class AnalyticsExportApiTest extends TestCase
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

    public function test_store_queues_a_new_export(): void
    {
        Queue::fake();
        [$tenant, $user] = $this->bootAnalyticsWorkspace();

        $this->postJson('/api/v1/analytics/exports', ['type' => 'conversations'])
            ->assertStatus(202)
            ->assertJsonPath('success', true);

        Queue::assertPushed(GenerateAnalyticsExport::class);

        $this->assertDatabaseHas('analytics_exports', [
            'user_id' => $user->id,
            'type' => 'conversations',
            'status' => 'pending',
        ], 'tenant');
    }

    public function test_store_requires_a_type(): void
    {
        $this->bootAnalyticsWorkspace();

        $this->postJson('/api/v1/analytics/exports', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_store_rejects_an_unknown_type(): void
    {
        $this->bootAnalyticsWorkspace();

        $this->postJson('/api/v1/analytics/exports', ['type' => 'not-a-real-export'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_index_lists_only_the_authenticated_users_exports(): void
    {
        [$tenant, $user] = $this->bootAnalyticsWorkspace();
        $other = $this->newTenantUser($tenant, 'support_agent');

        $this->seedAnalyticsExport($tenant, $user->id);
        $this->seedAnalyticsExport($tenant, $user->id);
        $this->seedAnalyticsExport($tenant, $other->id);

        $this->getJson('/api/v1/analytics/exports')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_is_isolated_between_tenants(): void
    {
        [$tenantA, $userA] = $this->bootAnalyticsWorkspace();
        $this->seedAnalyticsExport($tenantA, $userA->id);

        $tenantB = $this->newTenant();
        $userB = $this->newTenantUser($tenantB, 'owner');
        $this->prepareTenantDatabase($tenantB);
        $this->pointTenantConnectionAt($this->tenantDatabaseFiles[$tenantB->id]);
        $this->seedAnalyticsExport($tenantB, $userB->id);

        $this->establishTenantContext($tenantA, $userA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson('/api/v1/analytics/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_download_returns_not_found_for_an_unknown_export(): void
    {
        $this->bootAnalyticsWorkspace();

        $this->getJson('/api/v1/analytics/exports/999999/download')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_download_returns_not_found_when_the_export_is_not_ready(): void
    {
        [$tenant, $user] = $this->bootAnalyticsWorkspace();
        $export = $this->seedAnalyticsExport($tenant, $user->id, ['status' => 'pending']);

        $this->getJson("/api/v1/analytics/exports/{$export->id}/download")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_download_streams_a_ready_export(): void
    {
        Storage::fake('local');
        [$tenant, $user] = $this->bootAnalyticsWorkspace();

        $export = $this->seedAnalyticsExport($tenant, $user->id, [
            'status' => 'completed',
            'file_path' => 'analytics-exports/1/report.csv',
            'file_name' => 'report.csv',
            'row_count' => 1,
            'completed_at' => now(),
        ]);

        Storage::disk('local')->put('analytics-exports/1/report.csv', "a,b\n1,2\n");

        $this->getJson("/api/v1/analytics/exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('report.csv');
    }

    public function test_download_is_forbidden_for_another_users_export_without_permission(): void
    {
        [$tenant, $user] = $this->bootAnalyticsWorkspace('support_agent');
        $owner = $this->newTenantUser($tenant, 'owner');

        $export = $this->seedAnalyticsExport($tenant, $owner->id, [
            'status' => 'completed',
            'file_path' => 'analytics-exports/9/secret.csv',
            'file_name' => 'secret.csv',
        ]);

        $this->getJson("/api/v1/analytics/exports/{$export->id}/download")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_download_is_isolated_between_tenants(): void
    {
        $tenantA = $this->newTenant();
        $userA = $this->newTenantUser($tenantA, 'owner');
        $this->newSubscription($tenantA);
        $this->prepareTenantDatabase($tenantA);

        $tenantB = $this->newTenant();
        $userB = $this->newTenantUser($tenantB, 'owner');
        $this->prepareTenantDatabase($tenantB);
        $this->pointTenantConnectionAt($this->tenantDatabaseFiles[$tenantB->id]);
        $exportB = $this->seedAnalyticsExport($tenantB, $userB->id);

        $this->establishTenantContext($tenantA, $userA);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->getJson("/api/v1/analytics/exports/{$exportB->id}/download")
            ->assertStatus(404);

        $this->pointTenantConnectionAt($this->tenantDatabaseFiles[$tenantB->id]);
        $this->assertDatabaseHas('analytics_exports', ['id' => $exportB->id], 'tenant');
    }

    public function test_download_now_requires_the_export_permission(): void
    {
        $this->bootAnalyticsWorkspace('support_agent');

        $this->getJson('/api/v1/analytics/exports/download-now?type=conversations')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
