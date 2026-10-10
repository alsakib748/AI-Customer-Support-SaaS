<?php

namespace App\Health\Enums;

/**
 * Canonical health statuses shared by the backend and the dashboard.
 */
enum HealthStatus: string
{
    case Healthy   = 'healthy';
    case Degraded  = 'degraded';
    case Unhealthy = 'unhealthy';
    case Unknown   = 'unknown';
    case Disabled  = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Healthy   => 'Healthy',
            self::Degraded  => 'Degraded',
            self::Unhealthy => 'Unhealthy',
            self::Unknown   => 'Unknown',
            self::Disabled  => 'Disabled',
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::Healthy;
    }

    public function isFailure(): bool
    {
        return $this === self::Unhealthy;
    }

    public function isDegraded(): bool
    {
        return $this === self::Degraded;
    }

    public function isUnknown(): bool
    {
        return $this === self::Unknown;
    }

    public function isDisabled(): bool
    {
        return $this === self::Disabled;
    }

    /**
     * True when the status represents a working capability (healthy or degraded).
     */
    public function isOperational(): bool
    {
        return in_array($this, [self::Healthy, self::Degraded], true);
    }

    /**
     * Ordering used when reducing many statuses into one.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Disabled  => 0,
            self::Healthy   => 1,
            self::Unknown   => 2,
            self::Degraded  => 3,
            self::Unhealthy => 4,
        };
    }

    public static function fromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
