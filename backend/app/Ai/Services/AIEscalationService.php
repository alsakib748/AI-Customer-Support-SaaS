<?php
// app/Ai/Services/AIEscalationService.php

namespace App\Ai\Services;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\Tenant\Ticket;
use App\Services\Ticket\TicketService;
use Illuminate\Support\Facades\Log;

class AIEscalationService
{
    protected TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * Escalate a conversation to human agent
     */
    public function escalateConversation(Conversation $conversation, string $reason): array
    {
        try {
            // Check if already escalated
            if ($conversation->escalated ?? false) {
                return [
                    'success' => false,
                    'message' => 'Conversation is already escalated.',
                ];
            }

            // Update conversation
            $conversation->update([
                'escalated' => true,
                'escalated_reason' => $reason,
                'escalated_at' => now(),
                'status' => 'pending',
                'priority' => $this->determinePriority($reason),
            ]);

            // Create system message
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'system',
                'sender_id' => null,
                'message_type' => 'system_event',
                'content' => "AI escalated this conversation to a human agent. Reason: {$reason}",
                'is_internal' => true,
            ]);

            Log::info('Conversation escalated by AI', [
                'conversation_id' => $conversation->id,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => 'Conversation escalated successfully.',
                'conversation' => $conversation,
            ];

        } catch (\Exception $e) {
            Log::error('Escalation failed:', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to escalate conversation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create ticket from escalated conversation
     */
    public function createTicketFromEscalation(Conversation $conversation): ?Ticket
    {
        try {
            // Check if ticket already exists
            if ($conversation->ticket_id) {
                return Ticket::find($conversation->ticket_id);
            }

            // Get customer
            $customer = $conversation->customer;
            if (!$customer) {
                Log::error('Cannot create ticket: No customer found.', [
                    'conversation_id' => $conversation->id,
                ]);
                return null;
            }

            // Create ticket
            $ticketData = [
                'customer_id' => $customer->id,
                'conversation_id' => $conversation->id,
                'subject' => $conversation->subject ?? 'Escalated Conversation',
                'description' => "This ticket was created from an escalated conversation.\n\n" .
                    "Reason: " . ($conversation->escalated_reason ?? 'AI escalation') . "\n" .
                    "Conversation ID: " . $conversation->id,
                'priority' => $conversation->priority ?? 'normal',
                'type' => $this->determineType($conversation),
                'source' => 'ai',
            ];

            $ticket = $this->ticketService->createTicket($ticketData);

            // Link ticket to conversation
            $conversation->update(['ticket_id' => $ticket->id]);

            Log::info('Ticket created from escalated conversation', [
                'ticket_id' => $ticket->id,
                'conversation_id' => $conversation->id,
            ]);

            return $ticket;

        } catch (\Exception $e) {
            Log::error('Failed to create ticket from escalation:', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Determine priority based on reason
     */
    protected function determinePriority(string $reason): string
    {
        $highPriorityKeywords = ['urgent', 'critical', 'emergency', 'down', 'outage'];
        $normalPriorityKeywords = ['billing', 'refund', 'technical'];

        $reasonLower = strtolower($reason);

        foreach ($highPriorityKeywords as $keyword) {
            if (str_contains($reasonLower, $keyword)) {
                return 'high';
            }
        }

        foreach ($normalPriorityKeywords as $keyword) {
            if (str_contains($reasonLower, $keyword)) {
                return 'normal';
            }
        }

        return 'normal';
    }

    /**
     * Determine ticket type based on conversation
     */
    protected function determineType(Conversation $conversation): string
    {
        $subject = strtolower($conversation->subject ?? '');
        $description = strtolower($conversation->escalated_reason ?? '');

        $keywords = [
            'billing' => 'billing',
            'payment' => 'billing',
            'refund' => 'billing',
            'technical' => 'technical',
            'bug' => 'bug',
            'login' => 'account',
            'account' => 'account',
            'feature' => 'feature_request',
        ];

        foreach ($keywords as $keyword => $type) {
            if (str_contains($subject, $keyword) || str_contains($description, $keyword)) {
                return $type;
            }
        }

        return 'general';
    }
}
