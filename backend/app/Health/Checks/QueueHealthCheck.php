<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Derives queue health from the jobs table plus a worker heartbeat written by
 * HealthHeartbeatJob whenever an actual queue worker consumes it.
 */
class QueueHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'queue.default';
    }

    protected function perform(): HealthCheckResult
    {
        $driver = (string) config('queue.default');

        if ($driver === 'sync') {
            // A synchronous driver runs jobs inline; there is no asynchronous
            // queue or worker to monitor, so this is intentionally inactive in
            // this environment rather than healthy or broken.
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Disabled,
                'Queue driver is synchronous - no asynchronous worker to monitor',
                ['driver' => 'sync'],
            );
        }

        if ($driver !== 'database') {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, "Queue driver '{$driver}' is not monitored", ['driver' => $driver]);
        }

        $connection = DB::connection('central');

        $pending = (int) $connection->table('jobs')->count();
        $oldestRaw = $connection->table('jobs')->selectRaw('MIN(created_at) as oldest')->value('oldest');
        $oldestMinutes = $oldestRaw ? (int) max(0, abs(Carbon::parse($oldestRaw)->diffInMinutes(Carbon::now()))) : 0;
        $failed = (int) $connection->table('failed_jobs')->count();

        $workerHeartbeat = Cache::get(config('health.heartbeat.worker_key'));
        $workerAge = $workerHeartbeat !== null ? abs(now()->diffInSeconds(Carbon::createFromTimestamp((int) $workerHeartbeat))) : null;

        $metadata = [
            'driver'                   => $driver,
            'pending'                  => $pending,
            'oldest_age_minutes'       => $oldestMinutes,
            'failed'                   => $failed,
            'worker_heartbeat_age_sec' => $workerAge,
        ];

        $degradedPending = (int) $this->config('degraded_pending', 100);
        $unhealthyPending = (int) $this->config('unhealthy_pending', 1000);
        $degradedOldest = (int) $this->config('degraded_oldest_minutes', 10);
        $unhealthyOldest = (int) $this->config('unhealthy_oldest_minutes', 60);
        $degradedFailed = (int) $this->config('degraded_failed', 1);
        $unhealthyFailed = (int) $this->config('unhealthy_failed', 50);
        $workerStale = (int) $this->config('worker_stale_seconds', 300);

        $workerStaleFlag = $workerAge !== null && $workerAge > $workerStale;

        if (
            $failed >= $unhealthyFailed
            || $pending >= $unhealthyPending
            || $oldestMinutes >= $unhealthyOldest
            || ($workerAge === null && $pending > 0)
            || ($workerStaleFlag && $pending > 0)
        ) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, 'Queue backlog or stalled workers', $metadata);
        }

        if (
            $failed >= $degradedFailed
            || $pending >= $degradedPending
            || $oldestMinutes >= $degradedOldest
            || $workerStaleFlag
        ) {
            return HealthCheckResult::make($this->key(), HealthStatus::Degraded, 'Queue showing signs of pressure', $metadata);
        }

        return HealthCheckResult::make($this->key(), HealthStatus::Healthy, 'Queue processing normally', $metadata);
    }
}