<?php

namespace App\Listeners\Message;

use App\Events\Message\MessageCreated;
use App\Jobs\AI\ProcessCustomerMessage;
use App\Models\Tenant\AIConfiguration;
use Illuminate\Support\Facades\Log;

class ProcessMessageWithAI
{
    public function handle(MessageCreated $event): void
    {
        $message = $event->message;

        // Only process customer messages
        if ($message->sender_type !== 'customer') {
            return;
        }

        // Check if AI is enabled
        $config = AIConfiguration::first();
        if (!$config || !$config->enabled || !$config->auto_reply_enabled) {
            return;
        }

        // Check if conversation is escalated
        $conversation = $message->conversation;
        if ($conversation->escalated ?? false) {
            return;
        }

        // Check if conversation is closed
        if ($conversation->status === 'closed') {
            return;
        }

        // Dispatch AI job
        try {
            $tenant = tenancy()->tenant;

            if (!$tenant) {
                Log::warning('AI job not dispatched because no tenant is active', [
                    'message_id' => $message->id,
                ]);
                return;
            }

            ProcessCustomerMessage::dispatch($message->id, $tenant->id);

            Log::info('AI job dispatched for message', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch AI job:', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}