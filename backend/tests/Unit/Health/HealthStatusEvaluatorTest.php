<?php

namespace Tests\Unit\Health;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Services\HealthStatusEvaluator;
use Tests\TestCase;

class HealthStatusEvaluatorTest extends TestCase
{
    protected HealthStatusEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new HealthStatusEvaluator();
    }

    protected function entry(string $key, HealthStatus $status, bool $critical = false, bool $stale = false): array
    {
        return [
            'result'   => HealthCheckResult::make($key, $status),
            'critical' => $critical,
            'stale'    => $stale,
        ];
    }

    public function test_all_healthy_is_healthy(): void
    {
        $this->assertSame(
            HealthStatus::Healthy,
            $this->evaluator->evaluate([
                $this->entry('a', HealthStatus::Healthy, true),
                $this->entry('b', HealthStatus::Healthy, false),
            ]),
        );
    }

    public function test_any_critical_unhealthy_is_unhealthy(): void
    {
        $this->assertSame(
            HealthStatus::Unhealthy,
            $this->evaluator->evaluate([
                $this->entry('db', HealthStatus::Unhealthy, true),
                $this->entry('cache', HealthStatus::Healthy, false),
            ]),
        );
    }

    public function test_critical_unknown_dominates_degraded(): void
    {
        $result = $this->evaluator->evaluate([
            $this->entry('scheduler', HealthStatus::Unknown, true),
            $this->entry('cache', HealthStatus::Degraded, false),
        ]);

        $this->assertSame(HealthStatus::Unknown, $result);
    }

    public function test_stale_critical_result_is_unknown(): void
    {
        $result = $this->evaluator->evaluate([
            $this->entry('scheduler', HealthStatus::Healthy, true, stale: true),
        ]);

        $this->assertSame(HealthStatus::Unknown, $result);
    }

    public function test_non_critical_failure_is_degraded(): void
    {
        $result = $this->evaluator->evaluate([
            $this->entry('db', HealthStatus::Healthy, true),
            $this->entry('cache', HealthStatus::Unhealthy, false),
        ]);

        $this->assertSame(HealthStatus::Degraded, $result);
    }

    public function test_critical_degraded_is_degraded(): void
    {
        $result = $this->evaluator->evaluate([
            $this->entry('db', HealthStatus::Degraded, true),
        ]);

        $this->assertSame(HealthStatus::Degraded, $result);
    }

    public function test_disabled_checks_are_ignored(): void
    {
        $result = $this->evaluator->evaluate([
            $this->entry('unused', HealthStatus::Disabled, true),
        ]);

        $this->assertSame(HealthStatus::Healthy, $result);
    }

    public function test_empty_input_is_healthy(): void
    {
        $this->assertSame(HealthStatus::Healthy, $this->evaluator->evaluate([]));
    }
}