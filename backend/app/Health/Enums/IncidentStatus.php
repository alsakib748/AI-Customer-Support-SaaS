<?php

namespace App\Health\Enums;

enum IncidentStatus: string
{
    case Open         = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved     = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open         => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::Resolved     => 'Resolved',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Resolved;
    }
}
