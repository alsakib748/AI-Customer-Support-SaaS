<?php
// app/Ai/Services/AIService.php

namespace App\Ai\Services;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\AIUsage;
use App\Models\Tenant\AILog;
use App\Ai\Agents\CustomerSupportAgent;
use App\Ai\Tools\SearchKnowledgeBaseTool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AIService
{
    protected $configuration;
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->configuration = AIConfiguration::first() ?? AIConfiguration::getDefault();
        $this->geminiService = $geminiService;
    }

    /**
     * Process a customer message with AI - Gemini
     */
    public function processMessage(Message $message): array
    {
        try {
            // Check if AI is enabled
            if (!$this->configuration->enabled || !$this->configuration->auto_reply_enabled) {
                return ['success' => false, 'message' => 'AI is disabled for this tenant.'];
            }

            // Load conversation and customer
            $conversation = $message->conversation;
            $customer = $conversation->customer;

            // Check if AI already responded to this message
            if ($this->hasAIResponse($message)) {
                return ['success' => false, 'message' => 'AI already responded to this message.'];
            }

            // Check if conversation is escalated
            if ($conversation->escalated ?? false) {
                return ['success' => false, 'message' => 'Conversation is escalated to human agent.'];
            }

            // Build context
            $context = $this->buildContext($conversation, $customer);

            // Search knowledge base
            $knowledge = $this->searchKnowledgeBase($message->content);

            // Build prompt
            $prompt = $this->buildPrompt($context, $knowledge, $message->content);

            // Generate response using Gemini
            $startTime = microtime(true);
            $response = $this->geminiService->generateContent($prompt, [
                'temperature' => $this->configuration->temperature,
                'maxOutputTokens' => $this->configuration->max_tokens,
            ]);
            $duration = (microtime(true) - $startTime) * 1000;

            // Log the AI execution
            $this->logAIExecution($message, $response, $duration);

            // Track usage
            $this->trackUsage($message, $response);

            // Store AI response as message
            if ($response['success'] && isset($response['content'])) {
                $aiMessage = $this->storeAIResponse($message, $response['content']);

                // Update conversation last message
                $conversation->update(['last_message_at' => now()]);

                return [
                    'success' => true,
                    'message' => 'AI response generated.',
                    'ai_message' => $aiMessage,
                    'response' => $response['content'],
                    'metadata' => [
                        'provider' => 'gemini',
                        'model' => $this->configuration->model,
                        'usage' => $response['usage'] ?? [],
                    ],
                ];
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('AI processing failed:', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'AI processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build context for AI
     */
    protected function buildContext(Conversation $conversation, $customer): array
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
                    'created_at' => $msg->created_at->toISOString(),
                ];
            })
            ->toArray();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
            ],
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company' => $customer->company_name,
                'total_conversations' => $customer->total_conversations,
                'total_tickets' => $customer->total_tickets,
            ] : null,
            'messages' => $messages,
        ];
    }

    /**
     * Build prompt for Gemini
     */
    protected function buildPrompt(array $context, array $knowledge, string $userMessage): string
    {
        $systemPrompt = $this->configuration->system_prompt ?? $this->configuration->getDefaultSystemPrompt();
        $customInstructions = $this->configuration->custom_instructions ?? '';

        $prompt = $systemPrompt . "\n\n";

        // Customer context
        if ($context['customer']) {
            $prompt .= "Customer Information:\n";
            $prompt .= "Name: " . ($context['customer']['name'] ?? 'Unknown') . "\n";
            $prompt .= "Email: " . ($context['customer']['email'] ?? 'Unknown') . "\n";
            $prompt .= "Company: " . ($context['customer']['company'] ?? 'Unknown') . "\n";
            $prompt .= "Total Conversations: " . ($context['customer']['total_conversations'] ?? 0) . "\n";
            $prompt .= "Total Tickets: " . ($context['customer']['total_tickets'] ?? 0) . "\n\n";
        }

        // Conversation context
        $prompt .= "Conversation Subject: " . ($context['conversation']['subject'] ?? 'No subject') . "\n";
        $prompt .= "Conversation Status: " . ($context['conversation']['status'] ?? 'open') . "\n\n";

        // Recent messages
        if (!empty($context['messages'])) {
            $prompt .= "Recent Messages:\n";
            foreach ($context['messages'] as $msg) {
                $sender = $msg['sender_type'] === 'customer' ? 'Customer' :
                    ($msg['sender_type'] === 'agent' ? 'Agent' :
                        ($msg['sender_type'] === 'ai' ? 'AI' : 'System'));
                $prompt .= "- {$sender}: {$msg['content']}\n";
            }
            $prompt .= "\n";
        }

        // Knowledge base
        if (!empty($knowledge)) {
            $prompt .= "Relevant Knowledge Base Information:\n";
            foreach ($knowledge as $item) {
                $prompt .= "### " . ($item['title'] ?? 'Untitled') . "\n";
                $prompt .= ($item['content'] ?? '') . "\n\n";
            }
        }

        // Custom instructions
        if ($customInstructions) {
            $prompt .= "Additional Instructions:\n" . $customInstructions . "\n\n";
        }

        // Current message
        $prompt .= "Customer Message: " . $userMessage . "\n\n";
        $prompt .= "Please provide a helpful response to the customer.";

        return $prompt;
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
            $result = $tool->execute(['query' => $query, 'limit' => 5]);

            return $result['success'] ? $result['results'] : [];

        } catch (\Exception $e) {
            Log::error('Knowledge base search failed:', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Store AI response as message
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
                'model' => $this->configuration->model,
                'original_message_id' => $originalMessage->id,
            ],
        ]);
    }

    /**
     * Check if AI already responded to a message
     */
    protected function hasAIResponse(Message $message): bool
    {
        return Message::where('conversation_id', $message->conversation_id)
            ->where('sender_type', 'ai')
            ->whereJsonContains('metadata->original_message_id', $message->id)
            ->exists();
    }

    /**
     * Log AI execution
     */
    protected function logAIExecution(Message $message, array $response, float $duration): void
    {
        try {
            AILog::create([
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'agent' => 'customer_support',
                'provider' => 'gemini',
                'model' => $this->configuration->model,
                'duration_ms' => (int) $duration,
                'status' => $response['success'] ? 'success' : 'failed',
                'error_message' => $response['success'] ? null : ($response['error'] ?? null),
                'metadata' => [
                    'usage' => $response['usage'] ?? [],
                    'configuration' => [
                        'temperature' => $this->configuration->temperature,
                        'max_tokens' => $this->configuration->max_tokens,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log AI execution:', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Track AI usage
     */
    protected function trackUsage(Message $message, array $response): void
    {
        try {
            $usage = $response['usage'] ?? [];
            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // Gemini pricing (approximate)
            $inputCost = ($inputTokens / 1000000) * 0.075; // $0.075 per 1M tokens
            $outputCost = ($outputTokens / 1000000) * 0.30; // $0.30 per 1M tokens
            $estimatedCost = $inputCost + $outputCost;

            AIUsage::create([
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'provider' => 'gemini',
                'model' => $this->configuration->model,
                'operation' => 'chat',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $estimatedCost,
                'metadata' => [
                    'success' => $response['success'] ?? false,
                    'finish_reason' => $response['finish_reason'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track AI usage:', ['error' => $e->getMessage()]);
        }
    }
}