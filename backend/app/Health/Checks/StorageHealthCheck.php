<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageHealthCheck extends AbstractHealthCheck
{
    protected const PROBE_PREFIX = '.health-probe-';

    public function key(): string
    {
        return 'storage.default';
    }

    protected function perform(): HealthCheckResult
    {
        $disk = Storage::disk((string) config('filesystems.default'));
        $name = self::PROBE_PREFIX.Str::uuid()->toString();
        $token = Str::random(32);

        $started = microtime(true);
        $disk->put($name, $token);
        $read = $disk->get($name);
        $disk->delete($name);
        $latency = (int) round((microtime(true) - $started) * 1000);

        $metadata = [
            'disk'       => (string) config('filesystems.default'),
            'latency_ms' => $latency,
        ];

        if ($read !== $token) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, 'Storage write/read round-trip failed.', $metadata);
        }

        $maxLatency = (int) $this->config('latency_ms', 1500);

        $status = $latency > $maxLatency ? HealthStatus::Degraded : HealthStatus::Healthy;

        return HealthCheckResult::make(
            $this->key(),
            $status,
            $status->isHealthy() ? 'Storage round-trip OK' : "Storage response time {$latency}ms",
            $metadata,
        );
    }
}