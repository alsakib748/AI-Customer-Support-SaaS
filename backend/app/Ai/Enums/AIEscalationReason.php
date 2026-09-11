<?php
// app/Ai/Enums/AIEscalationReason.php

namespace App\Ai\Enums;

enum AIEscalationReason: string
{
    case UNKNOWN_ANSWER = 'unknown_answer';
    case LOW_CONFIDENCE = 'low_confidence';
    case CUSTOMER_REQUESTED_HUMAN = 'customer_requested_human';
    case SENSITIVE_BUSINESS_CASE = 'sensitive_business_case';
    case TOOL_FAILURE = 'tool_failure';
    case POLICY_EXCEPTION = 'policy_exception';
    case REPEATED_FAILURE = 'repeated_failure';
    case OUT_OF_SCOPE = 'out_of_scope';

    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN_ANSWER => 'Unknown Answer',
            self::LOW_CONFIDENCE => 'Low Confidence',
            self::CUSTOMER_REQUESTED_HUMAN => 'Customer Requested Human',
            self::SENSITIVE_BUSINESS_CASE => 'Sensitive Business Case',
            self::TOOL_FAILURE => 'Tool Failure',
            self::POLICY_EXCEPTION => 'Policy Exception',
            self::REPEATED_FAILURE => 'Repeated Failure',
            self::OUT_OF_SCOPE => 'Out of Scope',
        };
    }
}