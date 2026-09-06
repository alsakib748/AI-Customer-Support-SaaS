<?php
// app/Services/Message/MessageService.php

namespace App\Services\Message;

use App\Events\Message\MessageCreated;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MessageService
{
    protected $tenant;
    protected $currentUser;

    public function __construct()
    {
        $this->tenant = app('current_tenant');
        $this->currentUser = auth()->user();
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(Conversation $conversation, array $filters = []): LengthAwarePaginator
    {
        // Verify conversation belongs to tenant
        $this->verifyConversationAccess($conversation);

        $query = Message::forConversation($conversation->id);

        // Include internal messages for agents/admins
        $user = auth()->user();
        $isAgent = $user && $user->hasPermissionTo('conversations.view');

        if (!$isAgent) {
            // Customers don't see internal messages
            $query->public();
        }

        // Filter by message type
        if (!empty($filters['message_type'])) {
            $query->where('message_type', $filters['message_type']);
        }

        // Filter by sender type
        if (!empty($filters['sender_type'])) {
            $query->where('sender_type', $filters['sender_type']);
        }

        // Order by oldest first (for chat display)
        $query->orderBy('created_at', 'asc');

        $perPage = $filters['per_page'] ?? 30;
        return $query->paginate($perPage);
    }

    /**
     * Get a single message
     */
    public function getMessage(Message $message): Message
    {
        // Verify conversation belongs to tenant
        $this->verifyConversationAccess($message->conversation);

        // Check if user can see internal messages
        if ($message->is_internal) {
            $user = auth()->user();
            if (!$user || !$user->hasPermissionTo('conversations.view')) {
                throw ValidationException::withMessages([
                    'message' => ['You do not have permission to view this message.'],
                ]);
            }
        }

        return $message;
    }

    /**
     * Create a customer message
     */
    public function createCustomerMessage(Conversation $conversation, Customer $customer, array $data): Message
    {
        $this->verifyCustomerBelongsToConversation($conversation, $customer);

        return $this->createMessage($conversation, [
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'message_type' => 'text',
            'content' => $data['content'],
            'is_internal' => false,
        ]);
    }

    /**
     * Create an agent message
     */
    public function createAgentMessage(Conversation $conversation, array $data): Message
    {
        $user = auth()->user();

        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => ['You must be authenticated to send messages.'],
            ]);
        }

        // Verify user belongs to tenant
        $this->verifyUserInTenant($user->id);

        // Check permission
        if (!$user->hasPermissionTo('conversations.reply')) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to reply to conversations.'],
            ]);
        }

        $isInternal = $data['is_internal'] ?? false;
        $messageType = $isInternal ? 'internal_note' : 'text';

        return $this->createMessage($conversation, [
            'sender_type' => 'agent',
            'sender_id' => $user->id,
            'message_type' => $messageType,
            'content' => $data['content'],
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * Create an AI message
     */
    public function createAIMessage(Conversation $conversation, array $data): Message
    {
        return $this->createMessage($conversation, [
            'sender_type' => 'ai',
            'sender_id' => null,
            'message_type' => 'text',
            'content' => $data['content'],
            'is_internal' => false,
            'metadata' => $data['metadata'] ?? [
                'model' => 'ai-assistant',
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Create a system message
     */
    public function createSystemMessage(Conversation $conversation, string $event, array $data = []): Message
    {
        $content = $data['content'] ?? $this->getSystemEventMessage($event, $data);

        return $this->createMessage($conversation, [
            'sender_type' => 'system',
            'sender_id' => null,
            'message_type' => 'system_event',
            'content' => $content,
            'is_internal' => true,
            'metadata' => array_merge($data, ['event' => $event]),
        ]);
    }

    /**
     * Create an internal note
     */
    public function createInternalNote(Conversation $conversation, array $data): Message
    {
        $user = auth()->user();

        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => ['You must be authenticated to add notes.'],
            ]);
        }

        // Check permission
        if (!$user->hasPermissionTo('conversations.reply')) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to add notes.'],
            ]);
        }

        return $this->createMessage($conversation, [
            'sender_type' => 'agent',
            'sender_id' => $user->id,
            'message_type' => 'internal_note',
            'content' => $data['content'],
            'is_internal' => true,
        ]);
    }

    /**
     * Core message creation with transaction
     */
    protected function createMessage(Conversation $conversation, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $data) {
            // Create message
            $message = $conversation->messages()->create($data);

            // Update conversation last_message_at
            $conversation->update([
                'last_message_at' => $message->created_at,
            ]);

            // If message is from customer, update customer last activity
            if ($data['sender_type'] === 'customer' && $data['sender_id']) {
                $customer = Customer::find($data['sender_id']);
                if ($customer) {
                    $customer->update(['last_contacted_at' => now()]);
                }
            }

            Log::info('Message created', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_type' => $data['sender_type'],
                'user_id' => auth()->id(),
            ]);

            // Dispatch event for future AI processing
            event(new MessageCreated($message));

            return $message;
        });
    }

    /**
     * Delete a message (soft delete)
     */
    public function deleteMessage(Message $message): bool
    {
        // Verify conversation belongs to tenant
        $this->verifyConversationAccess($message->conversation);

        // Check permission
        $user = auth()->user();
        if (!$user || !$user->hasPermissionTo('conversations.delete')) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to delete messages.'],
            ]);
        }

        // Don't allow deleting system/AI messages
        if (in_array($message->sender_type, ['system', 'ai'])) {
            throw ValidationException::withMessages([
                'message' => ['System and AI messages cannot be deleted.'],
            ]);
        }

        $message->delete();

        Log::info('Message deleted', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Get system event message
     */
    protected function getSystemEventMessage(string $event, array $data = []): string
    {
        $messages = [
            'conversation_assigned' => 'Conversation assigned to user #' . ($data['assigned_user_id'] ?? 'unknown'),
            'conversation_unassigned' => 'Conversation unassigned',
            'conversation_resolved' => 'Conversation resolved',
            'conversation_reopened' => 'Conversation reopened',
            'conversation_closed' => 'Conversation closed',
            'conversation_created' => 'Conversation created',
            'ai_escalated' => 'AI escalated to human agent',
        ];

        return $messages[$event] ?? $event;
    }

    /**
     * Verify conversation belongs to current tenant
     */
    protected function verifyConversationAccess(Conversation $conversation): void
    {
        if (!$this->tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['No tenant context found.'],
            ]);
        }

        // Since we're in tenant database, the conversation is automatically scoped
        // But we verify the conversation exists
        if (!$conversation->exists) {
            throw ValidationException::withMessages([
                'conversation' => ['Conversation not found.'],
            ]);
        }
    }

    /**
     * Verify customer belongs to conversation
     */
    protected function verifyCustomerBelongsToConversation(Conversation $conversation, Customer $customer): void
    {
        if ($conversation->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'customer' => ['Customer does not belong to this conversation.'],
            ]);
        }
    }

    /**
     * Verify user belongs to tenant
     */
    protected function verifyUserInTenant(int $userId): void
    {
        if (!$this->tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['No tenant context found.'],
            ]);
        }

        $exists = DB::connection('central')
            ->table('tenant_user')
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'user' => ['User does not belong to this workspace.'],
            ]);
        }
    }
}