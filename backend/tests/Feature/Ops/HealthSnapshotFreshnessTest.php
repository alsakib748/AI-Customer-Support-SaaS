<?php

namespace Tests\Feature\Ops;

use App\Health\Services\HealthSnapshotService;
use App\Models\HealthCheckResult as HealthCheckResultModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 6 WS4 regression: the dashboard snapshot is cached and `?fresh=true`
 * bypasses that cache so operators cannot be shown a stale panel without an
 * explicit request for a rebuild.
 */
class HealthSnapshotFreshnessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['cache.default' => 'array']);

        Schema::connection('central')->create('health_check_results', function ($table) {
            $table->id();
            $table->string('check_key', 80)->index();
            $table->string('status', 20)->index();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->datetime('checked_at')->index();
            $table->timestamps();
        });

        Schema::connection('central')->create('platform_incidents', function ($table) {
            $table->id();
            $table->string('fingerprint', 120)->unique();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('severity', 20)->index();
            $table->string('status', 20)->index();
            $table->string('source', 60)->index();
            $table->datetime('started_at')->index();
            $table->datetime('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->datetime('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        config([
            'health.stale_after_seconds' => 600,
            'health.checks' => [
                'db.primary' => ['label' => 'Database', 'critical' => true, 'enabled' => true],
                'cache.run' => ['label' => 'Cache', 'critical' => false, 'enabled' => true],
            ],
        ]);
    }

    protected function seedRow(string $key, string $status): void
    {
        HealthCheckResultModel::create([
            'check_key' => $key,
            'status' => $status,
            'summary' => 'summary-'.$status,
            'metadata' => [],
            'duration_ms' => 5,
            'checked_at' => now(),
        ]);
    }

    public function test_snapshot_is_cached_and_fresh_bypasses_the_cache(): void
    {
        $this->seedRow('db.primary', 'healthy');
        $this->seedRow('cache.run', 'healthy');

        $service = app(HealthSnapshotService::class);

        $this->assertSame('healthy', $service->get()['overall']);

        // Change the stored results; a non-fresh call must keep serving the cache.
        HealthCheckResultModel::query()->delete();
        $this->seedRow('db.primary', 'unhealthy');
        $this->seedRow('cache.run', 'unhealthy');

        $this->assertSame('healthy', $service->get()['overall'], 'the cached snapshot must be served');

        $fresh = $service->get(fresh: true);
        $this->assertSame('unhealthy', $fresh['overall'], 'fresh must rebuild from stored results');
    }
}
