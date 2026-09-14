<?php
// app/Support/Analytics/ExportType.php

namespace App\Support\Analytics;

enum ExportType: string
{
    case CONVERSATIONS = 'conversations';
    case CUSTOMERS = 'customers';
    case AGENTS = 'agents';
    case TICKETS = 'tickets';
    case AI = 'ai';
    case WIDGET = 'widget';

    public function label(): string
    {
        return match ($this) {
            self::CONVERSATIONS => 'Conversations',
            self::CUSTOMERS => 'Customers',
            self::AGENTS => 'Agents',
            self::TICKETS => 'Tickets',
            self::AI => 'AI Usage',
            self::WIDGET => 'Widget',
        };
    }
}