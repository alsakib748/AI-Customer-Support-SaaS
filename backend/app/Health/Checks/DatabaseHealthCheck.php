<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Support\Facades\DB;

class DatabaseHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'database.postgres';
    }

    protected function perform(): HealthCheckResult
    {
        $started = microtime(true);
        $peek = DB::connection('central')->selectOne('SELECT 1');
        $latency = (int) round((microtime(true) - $started) * 1000);

        if ($peek === null) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, 'Database connection returned no response.');
        }

        $metadata = $this->gatherMetadata();
        $metadata['latency_ms'] = $latency;

        $degraded = (int) $this->config('degraded_latency_ms', 250);
        $unhealthy = (int) $this->config('unhealthy_latency_ms', 2000);

        $status = $latency > $unhealthy
            ? HealthStatus::Unhealthy
            : ($latency > $degraded ? HealthStatus::Degraded : HealthStatus::Healthy);

        return HealthCheckResult::make(
            $this->key(),
            $status,
            $status->isHealthy()
                ? 'Connection OK'
                : "Connection response time {$latency}ms",
            $metadata,
        );
    }

    /**
     * Best-effort capability counts. Missing tables must never fail the check.
     */
    protected function gatherMetadata(): array
    {
        $meta = [];

        foreach ([
            'tenants' => 'tenants',
            'users'   => 'users',
        ] as $label => $table) {
            try {
                $meta[$label] = (int) DB::connection('central')->table($table)->count();
            } catch (\Throwable) {
                // table not present yet
            }
        }

        return $meta;
    }
}