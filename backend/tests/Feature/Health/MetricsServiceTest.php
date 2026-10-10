<?php

namespace Tests\Feature\Health;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Services\MetricsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('central')->create('health_metric_samples', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key', 80)->index();
            $table->string('source', 80)->nullable();
            $table->decimal('value', 20, 4);
            $table->string('unit', 20)->nullable();
            $table->datetime('recorded_at')->index();
            $table->timestamps();
        });
    }

    protected function svc(): MetricsService
    {
        return $this->app->make(MetricsService::class);
    }

    public function test_record_check_writes_duration_and_status_samples(): void
    {
        $this->svc()->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Healthy, null, [], now())->withDuration(42));

        $rows = DB::connection('central')->table('health_metric_samples')->get();
        $this->assertCount(2, $rows);

        $duration = $rows->firstWhere('metric_key', 'check.duration_ms');
        $this->assertSame('db.primary', $duration->source);
        $this->assertEquals(42, (float) $duration->value);
        $this->assertSame('ms', $duration->unit);

        $status = $rows->firstWhere('metric_key', 'check.status');
        $this->assertEquals(1, (float) $status->value);
    }

    public function test_query_aggregates_and_filters_by_source(): void
    {
        $svc = $this->svc();
        $svc->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Healthy, null, [], Carbon::now())->withDuration(10));
        $svc->recordCheck(HealthCheckResult::make('cache.run', HealthStatus::Healthy, null, [], Carbon::now())->withDuration(30));

        $result = $svc->query('check.duration_ms', 'db.primary');

        $this->assertSame('db.primary', $result['source']);
        $this->assertSame(1, $result['count']);
        $this->assertSame(10.0, $result['min']);
        $this->assertSame(10.0, $result['max']);
        $this->assertSame(10.0, $result['avg']);

        $all = $svc->query('check.duration_ms');
        $this->assertSame(2, $all['count']);
        $this->assertSame(10.0, $all['min']);
        $this->assertSame(30.0, $all['max']);
    }

    public function test_query_respects_time_range(): void
    {
        $svc = $this->svc();
        $svc->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Healthy, null, [], Carbon::now()->subDays(3))->withDuration(10));
        $svc->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Healthy, null, [], Carbon::now())->withDuration(20));

        $result = $svc->query('check.duration_ms', 'db.primary', Carbon::now()->subDay());

        $this->assertSame(1, $result['count']);
        $this->assertSame(20.0, $result['min']);
    }

    public function test_disabled_results_are_not_sampled(): void
    {
        $svc = $this->svc();
        $svc->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Healthy, null, [], Carbon::now())->withDuration(10));
        $svc->recordCheck(HealthCheckResult::make('mail.default', HealthStatus::Disabled, null, [], Carbon::now())->withDuration(0));

        $rows = DB::connection('central')->table('health_metric_samples')->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['db.primary'], $rows->pluck('source')->unique()->values()->all());
    }

    public function test_availability_aggregates_status_samples(): void
    {
        $svc = $this->svc();
        $svc->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Healthy, null, [], Carbon::now())->withDuration(10));
        $svc->recordCheck(HealthCheckResult::make('db.primary', HealthStatus::Unhealthy, null, [], Carbon::now())->withDuration(10));
        $svc->recordCheck(HealthCheckResult::make('cache.run', HealthStatus::Healthy, null, [], Carbon::now())->withDuration(10));

        $all = $svc->availability();
        $this->assertSame(3, $all['total']);
        $this->assertSame(2, $all['available']);
        $this->assertSame(66.7, $all['pct']);

        $db = $svc->availability('db.primary');
        $this->assertSame(2, $db['total']);
        $this->assertSame(1, $db['available']);
        $this->assertSame(50.0, $db['pct']);
        $this->assertSame(50.0, $db['by_source']['db.primary']['pct']);
    }

    public function test_availability_is_null_when_no_samples(): void
    {
        $result = $this->svc()->availability();

        $this->assertSame(0, $result['total']);
        $this->assertNull($result['pct']);
    }
}
