<?php

namespace Tests\Feature\Health;

use App\Health\Checks\QueueHealthCheck;
use App\Health\Enums\HealthStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueueHealthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['cache.default' => 'array']);

        Schema::connection('central')->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::connection('central')->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        config([
            'queue.default' => 'database',
            'health.checks' => [
                'queue.default' => [
                    'label'                    => 'Queue',
                    'critical'                 => true,
                    'enabled'                  => true,
                    'degraded_pending'         => 2,
                    'unhealthy_pending'        => 10,
                    'degraded_oldest_minutes'  => 10,
                    'unhealthy_oldest_minutes' => 60,
                    'degraded_failed'          => 1,
                    'unhealthy_failed'         => 5,
                    'worker_stale_seconds'     => 300,
                ],
            ],
        ]);
    }

    protected function check(): HealthStatus
    {
        return $this->app->make(QueueHealthCheck::class)->run()->status;
    }

    protected function heartbeatWorker(?Carbon $at): void
    {
        if ($at !== null) {
            Cache::put('health:heartbeat:worker', $at->getTimestamp(), 900);
        }
    }

    protected function seedJobs(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::connection('central')->table('jobs')->insert([
                'queue'         => 'default',
                'payload'       => '{}',
                'attempts'      => 0,
                'reserved_at'   => null,
                'available_at'  => now()->getTimestamp(),
                'created_at'    => now()->getTimestamp(),
            ]);
        }
    }

    protected function seedFailed(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::connection('central')->table('failed_jobs')->insert([
                'uuid'       => 'uuid-'.$i,
                'connection' => 'database',
                'queue'      => 'default',
                'payload'    => '{}',
                'exception'  => 'test',
                'failed_at'  => now(),
            ]);
        }
    }

    public function test_empty_queue_with_fresh_worker_is_healthy(): void
    {
        $this->heartbeatWorker(Carbon::now()->subSeconds(10));

        $this->assertSame(HealthStatus::Healthy, $this->check());
    }

    public function test_pending_backlog_is_degraded(): void
    {
        $this->heartbeatWorker(Carbon::now()->subSeconds(10));
        $this->seedJobs(3);

        $this->assertSame(HealthStatus::Degraded, $this->check());
    }

    public function test_large_backlog_is_unhealthy(): void
    {
        $this->heartbeatWorker(Carbon::now()->subSeconds(10));
        $this->seedJobs(15);

        $this->assertSame(HealthStatus::Unhealthy, $this->check());
    }

    public function test_stale_worker_heartbeat_with_pending_is_unhealthy(): void
    {
        $this->heartbeatWorker(Carbon::now()->subSeconds(600));
        $this->seedJobs(1);

        $this->assertSame(HealthStatus::Unhealthy, $this->check());
    }

    public function test_failed_job_count_flips_status(): void
    {
        $this->heartbeatWorker(Carbon::now()->subSeconds(10));
        $this->seedFailed(2);

        $this->assertSame(HealthStatus::Degraded, $this->check());
    }

    public function test_synchronous_driver_is_reported_disabled(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertSame(HealthStatus::Disabled, $this->check());
    }
}