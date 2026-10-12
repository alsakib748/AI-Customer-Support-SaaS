<?php

namespace Tests\Feature\Ai;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class AIUsageApiTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithSupportDomain;
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

    public function test_usage_statistics_summarise_the_current_tenants_records(): void
    {
        $this->bootSupportWorkspace();
        $this->seedUsage(provider: 'gemini', tokens: 100, cost: 0.01);
        $this->seedUsage(provider: 'gemini', tokens: 250, cost: 0.02);

        $response = $this->getJson('/api/v1/ai/usage?period=month');

        $response->assertOk()
            ->assertJsonPath('data.total_requests', 2)
            ->assertJsonPath('data.total_tokens', 350);
        $this->assertEqualsWithDelta(0.03, $response->json('data.total_cost'), 0.000001);
    }

    public function test_usage_statistics_do_not_include_other_tenants(): void
    {
        $this->bootSupportWorkspace();
        $this->seedUsage(provider: 'gemini', tokens: 100, cost: 0.01);

        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->getJson('/api/v1/ai/usage')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 0)
            ->assertJsonPath('data.total_tokens', 0);
    }

    public function test_health_metrics_report_success_rate_and_status(): void
    {
        $this->bootSupportWorkspace();
        $this->seedLog(status: 'success', durationMs: 120);
        $this->seedLog(status: 'failed');
        $this->seedLog(status: 'success', durationMs: 80);

        $this->getJson('/api/v1/ai/health')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 3)
            ->assertJsonPath('data.status', 'degraded');
        $this->assertEqualsWithDelta(66.67, $this->getJson('/api/v1/ai/health')->json('data.success_rate'), 0.5);
    }

    public function test_analytics_returns_the_expected_shape(): void
    {
        $this->bootSupportWorkspace();
        $this->seedUsage(provider: 'gemini', tokens: 100, cost: 0.05);

        $this->getJson('/api/v1/ai/analytics')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 1)
            ->assertJsonStructure(['data' => ['total_cost', 'avg_cost_per_request', 'trend' => [['date', 'count']]]]);
    }

    public function test_logs_are_paginated_and_filterable(): void
    {
        $this->bootSupportWorkspace();
        $this->seedLog(status: 'success');
        $this->seedLog(status: 'failed');

        $all = $this->getJson('/api/v1/ai/logs');
        $response = $this->getJson('/api/v1/ai/logs?status=failed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'failed');
        $all->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_usage_endpoints_work_without_any_records(): void
    {
        $this->bootSupportWorkspace();

        $this->getJson('/api/v1/ai/usage')->assertOk()->assertJsonPath('data.total_requests', 0);
        $this->getJson('/api/v1/ai/health')->assertOk()->assertJsonPath('data.status', 'healthy');
        $this->getJson('/api/v1/ai/analytics')->assertOk()->assertJsonPath('data.total_requests', 0);
        $this->getJson('/api/v1/ai/logs')->assertOk()->assertJsonCount(0, 'data');
    }

    private function seedUsage(string $provider, int $tokens, float $cost): void
    {
        DB::connection('tenant')->table('ai_usage')->insert([
            'provider' => $provider,
            'model' => 'gemini-1.5-flash',
            'operation' => 'chat',
            'input_tokens' => intdiv($tokens, 2),
            'output_tokens' => $tokens - intdiv($tokens, 2),
            'total_tokens' => $tokens,
            'estimated_cost' => $cost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedLog(string $status, ?int $durationMs = null): void
    {
        DB::connection('tenant')->table('ai_logs')->insert([
            'agent' => 'chat',
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
            'status' => $status,
            'duration_ms' => $durationMs,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
