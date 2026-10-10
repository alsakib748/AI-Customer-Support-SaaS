<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CacheHealthCheck extends AbstractHealthCheck
{
    protected const PROBE_KEY_PREFIX = 'health:probe:';

    public function key(): string
    {
        return 'cache.default';
    }

    protected function perform(): HealthCheckResult
    {
        $key = self::PROBE_KEY_PREFIX.Str::uuid()->toString();
        $token = Str::random(32);

        $started = microtime(true);
        Cache::put($key, $token, 10);
        $read = Cache::get($key);
        Cache::forget($key);
        $latency = (int) round((microtime(true) - $started) * 1000);

        $metadata = ['store' => (string) config('cache.default'), 'latency_ms' => $latency];

        if ($read !== $token) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, 'Cache read/write round-trip failed.', $metadata);
        }

        $degraded = (int) $this->config('degraded_latency_ms', 50);

        $status = $latency > $degraded ? HealthStatus::Degraded : HealthStatus::Healthy;

        return HealthCheckResult::make(
            $this->key(),
            $status,
            $status->isHealthy() ? 'Cache round-trip OK' : "Cache response time {$latency}ms",
            $metadata,
        );
    }
}