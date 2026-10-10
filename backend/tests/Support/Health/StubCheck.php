<?php

namespace Tests\Support\Health;

use App\Health\Checks\AbstractHealthCheck;
use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;

class StubCheck extends AbstractHealthCheck
{
    public static string $key = 'stub.check';

    public static HealthStatus $status = HealthStatus::Healthy;

    public static ?string $summary = 'stub summary';

    public function key(): string
    {
        return self::$key;
    }

    protected function perform(): HealthCheckResult
    {
        return HealthCheckResult::make(self::$key, self::$status, self::$summary, ['stub' => true]);
    }
}