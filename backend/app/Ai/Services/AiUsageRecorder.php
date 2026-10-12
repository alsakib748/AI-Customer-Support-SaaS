<?php

namespace App\Ai\Services;

use App\Models\Tenant\AILog;
use App\Models\Tenant\AIUsage;
use Illuminate\Support\Facades\Log;

/**
 * Single place that turns an AI provider result into persisted usage + audit
 * rows. Streaming and non-streaming paths both funnel through here so that
 * every provider call is accounted for consistently.
 *
 * Token counts that the provider did not report are flagged with
 * `tokens_estimated => true` rather than being presented as measured values.
 */
class AiUsageRecorder
{
    /**
     * Best-effort token estimate for providers that do not stream usage back.
     */
    public function estimateTokens(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        return max(1, (int) ceil(strlen($text) / 4));
    }

    public function estimateCost(string $provider, int $inputTokens, int $outputTokens): float
    {
        // Per-1M-token pricing. Kept in one place so cost reporting stays
        // consistent between streaming and non-streaming paths.
        $pricing = [
            'gemini' => ['input' => 0.30, 'output' => 0.30],
        ];

        $rates = $pricing[$provider] ?? ['input' => 0.0, 'output' => 0.0];

        return ($inputTokens / 1_000_000) * $rates['input']
            + ($outputTokens / 1_000_000) * $rates['output'];
    }

    /**
     * Persist a usage row.
     *
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int, estimated?: bool}  $usage
     */
    public function record(
        ?int $conversationId,
        ?int $messageId,
        string $provider,
        string $model,
        string $operation,
        array $usage = [],
        string $status = 'completed',
        ?string $finishReason = null,
        array $extraMetadata = [],
    ): ?AIUsage {
        $input = (int) ($usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));
        $estimated = (bool) ($usage['estimated'] ?? false);

        try {
            return AIUsage::create([
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'provider' => $provider,
                'model' => $model,
                'operation' => $operation,
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => $total,
                'estimated_cost' => $this->estimateCost($provider, $input, $output),
                'metadata' => array_merge([
                    'status' => $status,
                    'tokens_estimated' => $estimated,
                    'finish_reason' => $finishReason,
                ], $extraMetadata),
            ]);
        } catch (\Throwable $e) {
            // A duplicate (message_id, operation) claim or a transient DB error
            // must never break the request that is already being served.
            Log::warning('Failed to record AI usage', [
                'message_id' => $messageId,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Persist an AI execution log entry.
     */
    public function log(
        ?int $conversationId,
        ?int $messageId,
        string $provider,
        string $model,
        string $status,
        ?int $durationMs = null,
        ?string $errorMessage = null,
        array $metadata = [],
    ): ?AILog {
        try {
            return AILog::create([
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'agent' => 'customer_support',
                'provider' => $provider,
                'model' => $model,
                'duration_ms' => $durationMs,
                'status' => $status,
                'error_message' => $errorMessage,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record AI log', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
