<?php

namespace App\Jobs\AI;

use App\Ai\Services\AIService;
use App\Models\Tenant\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

class ProcessCustomerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    protected ?Message $message = null;
    protected int $messageId;
    protected string $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $messageId, string $tenantId)
    {
        $this->messageId = $messageId;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = \App\Models\Tenant::findOrFail($this->tenantId);
        Tenancy::initialize($tenant);

        try {
            $message = $this->message = Message::findOrFail($this->messageId);
            $aiService = app(AIService::class);

            Log::info('Processing customer message with AI', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
            ]);

            $result = $aiService->processMessage($message);

            if (!$result['success']) {
                Log::warning('AI processing failed', [
                    'message_id' => $message->id,
                    'reason' => $result['message'] ?? 'Unknown error',
                ]);

                // If AI fails and escalation is enabled, escalate the conversation
                $this->handleFailure($result);
            }

        } catch (\Exception $e) {
            Log::error('AI job failed:', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        } finally {
            Tenancy::end();
        }
    }

    protected function handleFailure(array $result): void
    {
        try {
            // Check if escalation is enabled
            $config = \App\Models\Tenant\AIConfiguration::first();
            if (!$config || !$config->auto_escalation_enabled) {
                return;
            }

            // Get conversation
            $conversation = Message::findOrFail($this->messageId)->conversation;

            // Escalate if not already escalated
            if (!($conversation->escalated ?? false)) {
                $conversation->update([
                    'escalated' => true,
                    'escalated_reason' => $result['message'] ?? 'AI processing failed',
                    'escalated_at' => now(),
                    'status' => 'pending',
                ]);

                // Create system message
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'system',
                    'sender_id' => null,
                    'message_type' => 'system_event',
                    'content' => 'AI escalated this conversation to a human agent due to processing issues.',
                    'is_internal' => true,
                ]);

                Log::info('Conversation escalated due to AI failure', [
                    'conversation_id' => $conversation->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle AI failure:', ['error' => $e->getMessage()]);
        }
    }

}