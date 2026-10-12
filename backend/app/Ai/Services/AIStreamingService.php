<?php

// app/Ai/Services/AIStreamingService.php

namespace App\Ai\Services;

use App\Ai\Tools\SearchKnowledgeBaseTool;
use App\Events\AI\AIResponseGenerated;
use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIStreamingService
{
    protected $configuration;

    protected GeminiService $geminiService;

    public function __construct(
        GeminiService $geminiService,
        protected AiUsageRecorder $recorder,
    ) {
        $this->configuration = AIConfiguration::first() ?? AIConfiguration::getDefault();
        $this->geminiService = $geminiService;
    }

    /**
     * Stream AI response
     */
    public function streamResponse(Conversation $conversation, Message $message): StreamedResponse
    {
        return response()->stream(function () use ($conversation, $message) {
            $startTime = microtime(true);
            $fullResponse = '';
            $chunksReceived = 0;
            $providerUsage = [];
            $errorMessage = null;
            $aiMessage = null;

            try {
                // Check if streaming is enabled
                if (! $this->configuration->streaming_enabled) {
                    echo 'data: '.json_encode([
                        'type' => 'error',
                        'message' => 'Streaming is not enabled for this tenant.',
                    ])."\n\n";

                    return;
                }

                // Send start event
                echo 'data: '.json_encode([
                    'type' => 'start',
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ])."\n\n";
                ob_flush();
                flush();

                // Build context and prompt
                $context = $this->buildContext($conversation);
                $knowledge = $this->searchKnowledgeBase($message->content);
                $prompt = $this->buildPrompt($context, $knowledge, $message->content);

                // Stream using Gemini
                $this->geminiService->streamContent($prompt, function ($chunk) use (&$fullResponse, &$chunksReceived) {
                    $chunksReceived++;
                    $fullResponse .= $chunk;

                    echo 'data: '.json_encode([
                        'type' => 'chunk',
                        'content' => $chunk,
                    ])."\n\n";
                    ob_flush();
                    flush();
                }, [
                    'temperature' => $this->configuration->temperature,
                    'maxOutputTokens' => $this->configuration->max_tokens,
                ]);

                // Check if streaming actually produced content
                if (empty($fullResponse) || $chunksReceived === 0) {
                    Log::warning('Streaming produced no content, falling back to non-streaming');

                    // Fallback to non-streaming
                    $result = $this->geminiService->generateContent($prompt, [
                        'temperature' => $this->configuration->temperature,
                        'maxOutputTokens' => $this->configuration->max_tokens,
                    ]);

                    if ($result['success']) {
                        $fullResponse = $result['content'];
                        $providerUsage = $result['usage'] ?? [];

                        echo 'data: '.json_encode([
                            'type' => 'chunk',
                            'content' => $fullResponse,
                        ])."\n\n";
                    } else {
                        $errorMessage = $result['error'] ?? 'AI provider returned no content.';
                    }
                }

                // Store AI message
                if (! empty($fullResponse)) {
                    $aiMessage = $this->storeAIResponse($message, $fullResponse);

                    echo 'data: '.json_encode([
                        'type' => 'complete',
                        'message_id' => $aiMessage->id,
                    ])."\n\n";
                }
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();

                Log::error('Streaming error:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'An error occurred while streaming the response.',
                ])."\n\n";
                ob_flush();
                flush();
            } finally {
                $this->recordStreamUsage(
                    $conversation,
                    $message,
                    $prompt ?? '',
                    $fullResponse,
                    $providerUsage,
                    $chunksReceived,
                    $errorMessage,
                    (microtime(true) - $startTime) * 1000,
                );

                if ($aiMessage !== null) {
                    event(new AIResponseGenerated($conversation, $aiMessage, [
                        'provider' => 'gemini',
                        'model' => $this->configuration->model,
                        'usage' => $providerUsage,
                        'streamed' => true,
                        'original_message_id' => $message->id,
                    ]));
                }
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Account for a streamed completion. Provider-reported usage is used when
     * available (non-streaming fallback); otherwise the counts are estimated and
     * flagged as such.
     */
    protected function recordStreamUsage(
        Conversation $conversation,
        Message $message,
        string $prompt,
        string $response,
        array $providerUsage,
        int $chunksReceived,
        ?string $errorMessage,
        float $durationMs,
    ): void {
        $status = $errorMessage === null && $response !== '' ? 'completed' : 'failed';

        if (! empty($providerUsage)) {
            $usage = $providerUsage;
        } else {
            $usage = [
                'prompt_tokens' => $this->recorder->estimateTokens($prompt),
                'completion_tokens' => $this->recorder->estimateTokens($response),
                'estimated' => true,
            ];
        }

        $this->recorder->record(
            $conversation->id,
            $message->id,
            'gemini',
            $this->configuration->model,
            'chat_stream',
            $usage,
            $status,
            null,
            ['streamed_chunks' => $chunksReceived],
        );

        $this->recorder->log(
            $conversation->id,
            $message->id,
            'gemini',
            $this->configuration->model,
            $status === 'completed' ? 'success' : 'failed',
            (int) $durationMs,
            $errorMessage,
            ['usage' => $usage, 'streamed_chunks' => $chunksReceived],
        );
    }

    /**
     * Build context for AI
     */
    protected function buildContext(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->where('is_internal', false)
            ->latest()
            ->limit(10)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return [
                    'sender_type' => $msg->sender_type,
                    'content' => $msg->content,
                ];
            })
            ->toArray();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
            ],
            'customer' => $conversation->customer ? [
                'name' => $conversation->customer->full_name,
                'email' => $conversation->customer->email,
                'company' => $conversation->customer->company_name,
            ] : null,
            'messages' => $messages,
        ];
    }

    /**
     * Search knowledge base
     */
    protected function searchKnowledgeBase(string $query): array
    {
        if (! $this->configuration->knowledge_base_enabled) {
            return [];
        }

        try {
            $tool = new SearchKnowledgeBaseTool;
            $result = $tool->execute(['query' => $query, 'limit' => 3]);

            return $result['success'] ? $result['results'] : [];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Build prompt for Gemini
     */
    protected function buildPrompt(array $context, array $knowledge, string $userMessage): string
    {
        $systemPrompt = $this->configuration->system_prompt ?? $this->configuration->getDefaultSystemPrompt();

        $prompt = $systemPrompt."\n\n";

        if ($context['customer']) {
            $prompt .= 'Customer: '.($context['customer']['name'] ?? 'Unknown')."\n";
            $prompt .= 'Company: '.($context['customer']['company'] ?? 'Unknown')."\n\n";
        }

        $prompt .= 'Subject: '.($context['conversation']['subject'] ?? 'No subject')."\n\n";

        if (! empty($knowledge)) {
            $prompt .= "Relevant Knowledge:\n";
            foreach ($knowledge as $item) {
                $prompt .= '### '.($item['title'] ?? '')."\n";
                $prompt .= ($item['content'] ?? '')."\n\n";
            }
        }

        $prompt .= 'Customer Message: '.$userMessage."\n\n";
        $prompt .= 'Please provide a helpful response.';

        return $prompt;
    }

    /**
     * Store AI response
     */
    protected function storeAIResponse(Message $originalMessage, string $response): Message
    {
        return Message::create([
            'conversation_id' => $originalMessage->conversation_id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'message_type' => 'text',
            'content' => $response,
            'is_internal' => false,
            'metadata' => [
                'ai_generated' => true,
                'provider' => 'gemini',
                'original_message_id' => $originalMessage->id,
            ],
        ]);
    }
}
