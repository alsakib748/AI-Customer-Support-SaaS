<?php

namespace App\Jobs\AI;

use App\Ai\Services\AIService;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Tenant;
use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\Message;
use App\Services\Billing\BillingLimitService;
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

    public function backoff(): array
    {
        return [10, 30, 60];
    }

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
        $tenant = Tenant::findOrFail($this->tenantId);
        Tenancy::initialize($tenant);

        $limits = app(BillingLimitService::class);
        $reservation = null;

        try {
            // Hard pre-flight gate: refuse to spend AI tokens once the tenant is
            // out of quota. This runs before the provider is ever called so an
            // over-limit tenant never incurs cost.
            try {
                $limits->enforce($tenant, 'ai.requests.monthly', 1);
            } catch (PlanLimitExceededException $e) {
                Log::warning('AI request blocked by billing limit', [
                    'message_id' => $this->messageId,
                    'tenant_id' => $this->tenantId,
                    'code' => $e->errorCode,
                    'data' => $e->errorData,
                ]);

                // Nothing was spent — do not retry, do not escalate.
                return;
            }

            // Concurrency-safe reservation: the read-then-compare enforce()
            // above can race, so atomically claim a request slot before the
            // provider is called.
            $reservation = $limits->reserve($tenant, 'ai.requests.monthly', 1);

            if ($reservation === null) {
                Log::warning('AI request blocked by quota reservation', [
                    'message_id' => $this->messageId,
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            $message = $this->message = Message::findOrFail($this->messageId);
            $aiService = app(AIService::class);

            Log::info('Processing customer message with AI', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
            ]);

            $result = $aiService->processMessage($message);

            if (! $result['success'] && empty($result['skipped'])) {
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

            throw $e;
        } finally {
            $limits->release($reservation);
            Tenancy::end();
        }
    }

    protected function handleFailure(array $result): void
    {
        try {
            // Check if escalation is enabled
            $config = AIConfiguration::first();
            if (! $config || ! $config->auto_escalation_enabled) {
                return;
            }

            // Get conversation
            $conversation = Message::findOrFail($this->messageId)->conversation;

            // Escalate if not already escalated
            if (! ($conversation->escalated ?? false)) {
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
