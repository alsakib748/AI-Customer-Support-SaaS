<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetConversationTool extends BaseTool
{

    public function name(): string
    {
        return 'get_conversation';
    }

    public function description(): string
    {
        return 'Get conversation details and history.';
    }

    public function parameters(): array
    {
        return [
            'conversation_id' => [
                'type' => 'integer',
                'description' => 'Conversation ID',
                'required' => true,
            ],
        ];
    }

    public function execute(array $parameters): array
    {
        try {
            if (!$this->isAuthorized()) {
                return $this->error('Unauthorized to access conversation.');
            }

            $conversationId = $parameters['conversation_id'] ?? null;

            if (!$conversationId) {
                return $this->error('Conversation ID is required.');
            }

            $conversation = Conversation::with(['customer', 'messages'])
                ->find($conversationId);

            if (!$conversation) {
                return $this->error('Conversation not found.');
            }

            // Verify conversation belongs to current tenant
            // (Already handled by tenant database)

            $result = [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'status_label' => $conversation->status_label,
                'priority' => $conversation->priority,
                'priority_label' => $conversation->priority_label,
                'channel' => $conversation->channel,
                'customer' => [
                    'id' => $conversation->customer?->id,
                    'name' => $conversation->customer?->full_name,
                    'email' => $conversation->customer?->email,
                ],
                'message_count' => $conversation->messages->count(),
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'created_at' => $conversation->created_at->toISOString(),
            ];

            // Include recent messages (last 10)
            $recentMessages = $conversation->messages()
                ->where('is_internal', false)
                ->latest()
                ->limit(10)
                ->get()
                ->reverse()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'sender_type' => $message->sender_type,
                        'content' => $message->content,
                        'created_at' => $message->created_at->toISOString(),
                    ];
                });

            $result['recent_messages'] = $recentMessages->toArray();

            $this->logExecution('get_conversation', $parameters, $result);

            return [
                'success' => true,
                'conversation' => $result,
                'message' => 'Conversation retrieved successfully.',
            ];

        } catch (\Exception $e) {
            Log::error('Get conversation failed:', ['error' => $e->getMessage()]);
            return $this->error('Failed to get conversation: ' . $e->getMessage());
        }
    }

    protected function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'conversation' => null,
        ];
    }

    // /**
    //  * Get the description of the tool's purpose.
    //  */
    // public function description(): Stringable|string
    // {
    //     return 'A description of the tool.';
    // }

    // /**
    //  * Execute the tool.
    //  */
    // public function handle(Request $request): Stringable|string
    // {
    //     //
    // }

    // /**
    //  * Get the tool's schema definition.
    //  */
    // public function schema(JsonSchema $schema): array
    // {
    //     return [
    //         'value' => $schema->string()->required(),
    //     ];
    // }
}