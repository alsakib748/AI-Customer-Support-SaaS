<?php

namespace App\Health\Enums;

enum IncidentSeverity: string
{
    case Info     = 'info';
    case Warning  = 'warning';
    case Error    = 'error';
    case Critical = 'critical';

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
}
