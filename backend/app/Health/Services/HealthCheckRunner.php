<?php

namespace App\Health\Services;

use App\Health\Contracts\HealthCheck;
use App\Health\DTOs\HealthCheckResult;
use InvalidArgumentException;

/**
 * Executes checks, persists their outcomes and records their metrics.
 */
class HealthCheckRunner
{
    public function __construct(
        protected HealthCheckRegistry $registry,
        protected HealthCheckStorage $storage,
        protected MetricsService $metrics,
    ) {
    }

    /**
     * @return HealthCheckResult[]
     */
    public function runAll(): array
    {
        return array_map(fn (HealthCheck $check) => $this->run($check), $this->registry->all());
    }

    public function runOne(string $key): HealthCheckResult
    {
        $check = $this->registry->get($key);

        if (! $check) {
            throw new InvalidArgumentException("Unknown or disabled health check '{$key}'.");
        }

        return $this->run($check);
    }

    protected function run(HealthCheck $check): HealthCheckResult
    {
        $result = $check->run();

        $this->storage->persist($result);
        $this->metrics->recordCheck($result);

        return $result;
    }
}