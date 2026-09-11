<?php
// app/Ai/Context/ConversationContextBuilder.php

namespace App\Ai\Context;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use Illuminate\Support\Facades\Log;

class ConversationContextBuilder
{
    protected int $maxMessages = 20;
    protected int $maxTokens = 2000;

    /**
     * Build conversation context
     */
    public function build(Conversation $conversation): array
    {
        try {
            // Get recent messages
            $messages = $conversation->messages()
                ->where('is_internal', false)
                ->orderBy('created_at', 'asc')
                ->limit($this->maxMessages)
                ->get();

            $context = [
                'conversation' => [
                    'id' => $conversation->id,
                    'subject' => $conversation->subject,
                    'status' => $conversation->status,
                    'priority' => $conversation->priority,
                    'channel' => $conversation->channel,
                    'created_at' => $conversation->created_at->toISOString(),
                ],
                'customer' => $this->buildCustomerContext($conversation->customer),
                'messages' => $this->buildMessagesContext($messages),
                'summary' => $this->buildSummary($messages),
            ];

            return $context;

        } catch (\Exception $e) {
            Log::error('Failed to build conversation context:', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            return [
                'conversation' => null,
                'customer' => null,
                'messages' => [],
                'summary' => null,
            ];
        }
    }

    /**
     * Build customer context
     */
    protected function buildCustomerContext($customer): ?array
    {
        if (!$customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'company' => $customer->company_name,
            'total_conversations' => $customer->total_conversations,
            'total_tickets' => $customer->total_tickets,
            'satisfaction_score' => $customer->satisfaction_score,
            'last_contacted_at' => $customer->last_contacted_at?->toISOString(),
        ];
    }

    /**
     * Build messages context
     */
    protected function buildMessagesContext($messages): array
    {
        return $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'content' => $this->truncateContent($message->content),
                'created_at' => $message->created_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Build conversation summary
     */
    protected function buildSummary($messages): ?string
    {
        if ($messages->isEmpty()) {
            return null;
        }

        // Get first and last message
        $first = $messages->first();
        $last = $messages->last();

        $summary = "Conversation started with: \"{$this->truncateContent($first->content, 100)}\"";

        if ($messages->count() > 1) {
            $summary .= "\nLatest message: \"{$this->truncateContent($last->content, 100)}\"";
        }

        return $summary;
    }

    /**
     * Truncate content to max length
     */
    protected function truncateContent(string $content, int $maxLength = 500): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength) . '...';
    }

    /**
     * Set max messages
     */
    public function setMaxMessages(int $maxMessages): self
    {
        $this->maxMessages = $maxMessages;
        return $this;
    }

    /**
     * Set max tokens
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }
}