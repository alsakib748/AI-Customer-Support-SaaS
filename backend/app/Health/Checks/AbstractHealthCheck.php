<?php

namespace App\Health\Checks;

use App\Health\Contracts\HealthCheck;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Support\HealthConfig;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base class enforcing timing + exception normalisation so concrete checks
 * focus purely on their probe logic.
 */
abstract class AbstractHealthCheck implements HealthCheck
{
    abstract protected function perform(): HealthCheckResult;

    public function label(): string
    {
        return (string) HealthConfig::check($this->key(), 'label', $this->key());
    }

    public function critical(): bool
    {
        return (bool) HealthConfig::check($this->key(), 'critical', false);
    }

    public function enabled(): bool
    {
        return (bool) HealthConfig::check($this->key(), 'enabled', true);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return HealthConfig::check($this->key(), $key, $default);
    }

    final public function run(): HealthCheckResult
    {
        $started = microtime(true);

        try {
            $result = $this->perform();
        } catch (Throwable $e) {
            $result = HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unhealthy,
                'Check failed: '.Str::limit($e->getMessage(), 180),
                ['error' => class_basename($e)],
            );
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);

        return $result->withDuration($elapsed);
    }
}