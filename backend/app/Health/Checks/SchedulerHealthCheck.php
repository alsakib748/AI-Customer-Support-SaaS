<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Confirms the scheduler is alive via a heartbeat written by the
 * "health:heartbeat" scheduled command.
 */
class SchedulerHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'scheduler.heartbeat';
    }

    protected function perform(): HealthCheckResult
    {
        $heartbeat = Cache::get(config('health.heartbeat.scheduler_key'));
        $stale = (int) $this->config('stale_seconds', 180);

        if ($heartbeat === null) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unhealthy,
                'No scheduler heartbeat recorded yet',
                ['heartbeat_age_sec' => null],
            );
        }

        $age = (int) abs(now()->diffInSeconds(Carbon::createFromTimestamp((int) $heartbeat)));
        $metadata = ['heartbeat_age_sec' => $age];

        if ($age > $stale * 2) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, "Scheduler heartbeat {$age}s old", $metadata);
        }

        if ($age > $stale) {
            return HealthCheckResult::make($this->key(), HealthStatus::Degraded, "Scheduler heartbeat {$age}s old", $metadata);
        }

        return HealthCheckResult::make($this->key(), HealthStatus::Healthy, 'Scheduler heartbeat fresh', $metadata);
    }
}