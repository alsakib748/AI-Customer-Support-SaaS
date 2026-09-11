<?php
// app/Ai/Services/AIStreamingService.php

namespace App\Ai\Services;

use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIStreamingService
{
    protected $configuration;
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->configuration = AIConfiguration::first() ?? AIConfiguration::getDefault();
        $this->geminiService = $geminiService;
    }

    /**
     * Stream AI response
     */
    public function streamResponse(Conversation $conversation, Message $message): StreamedResponse
    {
        return response()->stream(function () use ($conversation, $message) {
            try {
                // Check if streaming is enabled
                if (!$this->configuration->streaming_enabled) {
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'message' => 'Streaming is not enabled for this tenant.',
                    ]) . "\n\n";
                    return;
                }

                // Send start event
                echo "data: " . json_encode([
                    'type' => 'start',
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]) . "\n\n";
                ob_flush();
                flush();

                // Build context and prompt
                $context = $this->buildContext($conversation);
                $knowledge = $this->searchKnowledgeBase($message->content);
                $prompt = $this->buildPrompt($context, $knowledge, $message->content);

                // Stream using Gemini
                $fullResponse = '';
                $chunksReceived = 0;

                $this->geminiService->streamContent($prompt, function ($chunk) use (&$fullResponse, &$chunksReceived) {
                    $chunksReceived++;
                    $fullResponse .= $chunk;

                    echo "data: " . json_encode([
                        'type' => 'chunk',
                        'content' => $chunk,
                    ]) . "\n\n";
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

                        echo "data: " . json_encode([
                            'type' => 'chunk',
                            'content' => $fullResponse,
                        ]) . "\n\n";
                    }
                }

                // Store AI message
                if (!empty($fullResponse)) {
                    $aiMessage = $this->storeAIResponse($message, $fullResponse);

                    echo "data: " . json_encode([
                        'type' => 'complete',
                        'message_id' => $aiMessage->id,
                    ]) . "\n\n";
                }

                // ob_flush();
                // flush();

            } catch (\Exception $e) {
                Log::error('Streaming error:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => 'Streaming error: ' . $e->getMessage(),
                ]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
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
        if (!$this->configuration->knowledge_base_enabled) {
            return [];
        }

        try {
            $tool = new \App\Ai\Tools\SearchKnowledgeBaseTool();
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

        $prompt = $systemPrompt . "\n\n";

        if ($context['customer']) {
            $prompt .= "Customer: " . ($context['customer']['name'] ?? 'Unknown') . "\n";
            $prompt .= "Company: " . ($context['customer']['company'] ?? 'Unknown') . "\n\n";
        }

        $prompt .= "Subject: " . ($context['conversation']['subject'] ?? 'No subject') . "\n\n";

        if (!empty($knowledge)) {
            $prompt .= "Relevant Knowledge:\n";
            foreach ($knowledge as $item) {
                $prompt .= "### " . ($item['title'] ?? '') . "\n";
                $prompt .= ($item['content'] ?? '') . "\n\n";
            }
        }

        $prompt .= "Customer Message: " . $userMessage . "\n\n";
        $prompt .= "Please provide a helpful response.";

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