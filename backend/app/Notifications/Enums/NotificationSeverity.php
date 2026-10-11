<?php

namespace App\Notifications\Enums;

enum NotificationSeverity: string
{
    case Info     = 'info';
    case Warning  = 'warning';
    case Error    = 'error';
    case Critical = 'critical';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Info     => 0,
            self::Warning  => 1,
            self::Error    => 2,
            self::Critical => 3,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * PrimeVue Tag severity token for the frontend.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Info     => 'info',
            self::Warning  => 'warn',
            self::Error    => 'danger',
            self::Critical => 'danger',
        };
    }
}
