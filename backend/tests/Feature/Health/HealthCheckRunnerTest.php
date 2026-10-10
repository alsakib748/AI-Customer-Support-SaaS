<?php

namespace Tests\Feature\Health;

use App\Health\Enums\HealthStatus;
use App\Health\Services\HealthCheckRunner;
use App\Models\HealthCheckResult as HealthCheckResultModel;
use App\Models\HealthMetricSample;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Health\StubCheck;
use Tests\TestCase;

class HealthCheckRunnerTest extends TestCase
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

        Schema::connection('central')->create('health_metric_samples', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key', 80)->index();
            $table->string('source', 80)->nullable();
            $table->decimal('value', 20, 4);
            $table->string('unit', 20)->nullable();
            $table->datetime('recorded_at')->index();
            $table->timestamps();
        });

        config([
            'health.check_classes'     => [StubCheck::class],
            'health.checks'            => [
                'stub.check' => ['label' => 'Stub', 'critical' => true, 'enabled' => true],
            ],
        ]);
    }

    public function test_run_all_persists_result_and_metrics(): void
    {
        $runner = $this->app->make(HealthCheckRunner::class);

        [$result] = $runner->runAll();

        $this->assertSame(StubCheck::$key, $result->key);
        $this->assertSame(HealthStatus::Healthy, $result->status);
        $this->assertSame(['stub' => true], $result->metadata);

        $row = DB::connection('central')->table('health_check_results')->get();
        $this->assertCount(1, $row);
        $this->assertSame('stub.check', $row->first()->check_key);
        $this->assertSame('healthy', $row->first()->status);

        $metrics = DB::connection('central')->table('health_metric_samples')->get();
        $this->assertCount(2, $metrics);
        $this->assertSame(['check.duration_ms', 'check.status'], $metrics->pluck('metric_key')->sort()->values()->all());
    }

    public function test_run_all_persists_failure_status_without_throwing(): void
    {
        StubCheck::$status = HealthStatus::Unhealthy;
        StubCheck::$summary = 'boom';

        $runner = $this->app->make(HealthCheckRunner::class);

        [$result] = $runner->runAll();

        $this->assertSame(HealthStatus::Unhealthy, $result->status);

        $row = DB::connection('central')->table('health_check_results')->first();
        $this->assertSame('unhealthy', $row->status);
        $this->assertSame('boom', $row->summary);
    }

    public function test_run_one_rejects_unknown_check(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(HealthCheckRunner::class)->runOne('does.not.exist');
    }
}