<?php
// app/Ai/Enums/AIAction.php

namespace App\Ai\Enums;

enum AIAction: string
{
    case RESPOND = 'respond';
    case ESCALATE = 'escalate';
    case CREATE_TICKET = 'create_ticket';
    case ASSIGN_AGENT = 'assign_agent';
    case REQUEST_INFO = 'request_info';

    public function isHighImpact(): bool
    {
        return in_array($this, [
            self::ESCALATE,
            self::CREATE_TICKET,
            self::ASSIGN_AGENT,
        ]);
    }
}