<?php

namespace Tests\Feature\Health;

use App\Models\HealthCheckResult as HealthCheckResultModel;
use App\Models\PlatformIncident;
use App\Health\Services\HealthSnapshotService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HealthSnapshotServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['cache.default' => 'array']);

        Schema::connection('central')->create('health_check_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_key', 80)->index();
            $table->string('status', 20)->index();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->datetime('checked_at')->index();
            $table->timestamps();
        });

        Schema::connection('central')->create('platform_incidents', function (Blueprint $table) {
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
                'cache.run'  => ['label' => 'Cache', 'critical' => false, 'enabled' => true],
                'ai.disabled' => ['label' => 'AI', 'critical' => false, 'enabled' => false],
            ],
        ]);
    }

    protected function snapshot(): array
    {
        return $this->app->make(HealthSnapshotService::class)->get(fresh: true);
    }

    protected function seedRow(string $key, string $status, ?Carbon $checkedAt = null, int $duration = 12): void
    {
        HealthCheckResultModel::create([
            'check_key'   => $key,
            'status'      => $status,
            'summary'     => 'summary-'.$status,
            'metadata'    => [],
            'duration_ms' => $duration,
            'checked_at'  => $checkedAt ?? Carbon::now(),
        ]);
    }

    public function test_unseen_checks_default_to_unknown(): void
    {
        $snapshot = $this->snapshot();

        $this->assertSame('unknown', $snapshot['overall']);
        $this->assertSame(2, $snapshot['counts']['unknown']);
        $this->assertSame(1, $snapshot['counts']['disabled']);

        $unknown = collect($snapshot['checks'])->firstWhere('key', 'db.primary');
        $this->assertSame('unknown', $unknown['status']);
        $this->assertTrue($unknown['critical']);
    }

    public function test_fresh_results_produce_healthy_overall(): void
    {
        $this->seedRow('db.primary', 'healthy');
        $this->seedRow('cache.run', 'healthy');

        $snapshot = $this->snapshot();

        $this->assertSame('healthy', $snapshot['overall']);
        $this->assertSame(2, $snapshot['counts']['healthy']);
        $this->assertNotNull(collect($snapshot['checks'])->firstWhere('key', 'db.primary')['checked_at']);
    }

    public function test_stale_critical_result_is_reported_unknown(): void
    {
        $this->seedRow('db.primary', 'healthy', Carbon::now()->subSeconds(700));

        $snapshot = $this->snapshot();

        $check = collect($snapshot['checks'])->firstWhere('key', 'db.primary');
        $this->assertTrue($check['stale']);
        $this->assertSame('unknown', $check['status']);
        $this->assertSame('unknown', $snapshot['overall']);
    }

    public function test_disabled_check_is_never_included_in_overall(): void
    {
        $snapshot = $this->snapshot();
        $disabled = collect($snapshot['checks'])->firstWhere('key', 'ai.disabled');

        $this->assertSame('disabled', $disabled['status']);
        $this->assertFalse($disabled['critical']);
    }

    public function test_stale_disabled_result_stays_disabled(): void
    {
        $checks = config('health.checks');
        $checks['mail.default'] = ['label' => 'Mail', 'critical' => false, 'enabled' => true];
        config(['health.checks' => $checks]);

        $this->seedRow('mail.default', 'disabled', Carbon::now()->subSeconds(700));

        $snapshot = $this->snapshot();

        $check = collect($snapshot['checks'])->firstWhere('key', 'mail.default');
        $this->assertNotNull($check);
        $this->assertTrue($check['stale']);
        $this->assertSame('disabled', $check['status']);
        $this->assertSame(2, $snapshot['counts']['disabled']);
    }

    public function test_per_check_stale_threshold_override(): void
    {
        $checks = config('health.checks');
        $checks['db.primary']['stale_after_seconds'] = 900;
        $checks['cache.run']['stale_after_seconds'] = 60;
        config(['health.checks' => $checks]);

        $this->seedRow('db.primary', 'healthy', Carbon::now()->subSeconds(700));
        $this->seedRow('cache.run', 'healthy', Carbon::now()->subSeconds(700));

        $snapshot = $this->snapshot();

        $database = collect($snapshot['checks'])->firstWhere('key', 'db.primary');
        $cache = collect($snapshot['checks'])->firstWhere('key', 'cache.run');

        $this->assertSame('healthy', $database['status']);
        $this->assertFalse($database['stale']);
        $this->assertSame(900, $database['stale_after_seconds']);

        $this->assertSame('unknown', $cache['status']);
        $this->assertTrue($cache['stale']);
        $this->assertSame(60, $cache['stale_after_seconds']);
    }
}