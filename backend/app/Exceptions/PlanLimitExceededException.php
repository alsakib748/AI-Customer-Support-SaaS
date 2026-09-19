<?php

namespace App\Exceptions;

use Exception;

class PlanLimitExceededException extends Exception
{
    public function __construct(
        string $message,
        public string $errorCode = 'PLAN_LIMIT_REACHED',
        public array $errorData = [],
        int $code = 403,
    ) {
        parent::__construct($message, $code);
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'data' => $this->errorData,
        ], $this->getCode() ?: 403);
    }
}
