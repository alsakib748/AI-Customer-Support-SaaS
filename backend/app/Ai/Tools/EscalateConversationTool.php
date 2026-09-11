<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Conversation;
use App\Services\Conversation\ConversationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EscalateConversationTool extends BaseTool
{

    protected ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        parent::__construct();
        $this->conversationService = $conversationService;
    }

    public function name(): string
    {
        return 'escalate_conversation';
    }

    public function description(): string
    {
        return 'Escalate a conversation to a human agent when the AI cannot resolve the issue.';
    }

    public function parameters(): array
    {
        return [
            'conversation_id' => [
                'type' => 'integer',
                'description' => 'Conversation ID to escalate',
                'required' => true,
            ],
            'reason' => [
                'type' => 'string',
                'description' => 'Reason for escalation',
                'required' => true,
            ],
        ];
    }

    public function execute(array $parameters): array
    {
        try {
            if (!$this->isAuthorized()) {
                return $this->error('Unauthorized to escalate conversations.');
            }

            $conversationId = $parameters['conversation_id'] ?? null;
            $reason = $parameters['reason'] ?? null;

            if (!$conversationId) {
                return $this->error('Conversation ID is required.');
            }

            if (!$reason) {
                return $this->error('Escalation reason is required.');
            }

            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return $this->error('Conversation not found.');
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
            // Could create a system message here

            $this->logExecution('escalate_conversation', $parameters, [
                'conversation_id' => $conversationId,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'escalated' => true,
                    'escalated_reason' => $reason,
                ],
                'message' => 'Conversation escalated to human agent.',
            ];

        } catch (\Exception $e) {
            Log::error('Escalation failed:', ['error' => $e->getMessage()]);
            return $this->error('Failed to escalate conversation: ' . $e->getMessage());
        }
    }

    protected function determinePriority(string $reason): string
    {
        $highPriorityKeywords = ['urgent', 'critical', 'emergency', 'down', 'outage'];
        $normalPriorityKeywords = ['billing', 'refund', 'technical', 'issue'];

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
