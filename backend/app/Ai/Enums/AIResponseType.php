<?php
// app/Ai/Enums/AIResponseType.php

namespace App\Ai\Enums;

enum AIResponseType: string
{
    case ANSWER = 'answer';
    case ASK_CLARIFICATION = 'ask_clarification';
    case SEARCH_KNOWLEDGE = 'search_knowledge';
    case CALL_TOOL = 'call_tool';
    case CREATE_TICKET = 'create_ticket';
    case ESCALATE = 'escalate';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::ANSWER => 'Answer',
            self::ASK_CLARIFICATION => 'Ask Clarification',
            self::SEARCH_KNOWLEDGE => 'Search Knowledge',
            self::CALL_TOOL => 'Call Tool',
            self::CREATE_TICKET => 'Create Ticket',
            self::ESCALATE => 'Escalate to Human',
            self::ERROR => 'Error',
        };
    }
}