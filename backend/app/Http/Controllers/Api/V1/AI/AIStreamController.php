<?php

namespace App\Http\Controllers\Api\V1\AI;

use App\Ai\Services\AIService;
use App\Ai\Services\AIStreamingService;
use App\Exceptions\PlanLimitExceededException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Services\Billing\BillingLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AIStreamController extends Controller
{
    protected AIStreamingService $streamingService;
    protected AIService $aiService;

    public function __construct(
        AIStreamingService $streamingService,
        AIService $aiService
    ) {
        $this->streamingService = $streamingService;
        $this->aiService = $aiService;
    }

    /**
     * Stream AI response
     */
    public function stream(Request $request, Conversation $conversation, Message $message,BillingLimitService $limits,)
    {
        try {

            // ============================================
            // 1. ENFORCE BILLING LIMITS FIRST
            // ============================================
            $tenant = app('current_tenant');
            $limits->enforce($tenant, 'ai.requests.monthly', 1);

            // Verify message belongs to conversation
            if ($message->conversation_id !== $conversation->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message does not belong to this conversation.',
                ], 404);
            }

            // AI can respond to messages from customers or agents.
            if (!in_array($message->sender_type, ['customer', 'agent'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only customer or agent messages can be streamed.',
                ], 400);
            }

            // Check if AI is enabled
            $config = AIConfiguration::first();
            if (!$config || !$config->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI is not enabled.',
                ], 400);
            }

            // Check if streaming is enabled
            if (!$config->streaming_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI streaming is not enabled.',
                ], 400);
            }

            // Check if conversation is escalated
            if ($conversation->escalated ?? false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation is escalated to human agent.',
                ], 400);
            }

            Log::info('AI stream requested', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);

            return $this->streamingService->streamResponse($conversation, $message);

        }catch (PlanLimitExceededException $e) {
        //  Structured 403 — frontend shows "upgrade" prompt
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'code'    => $e->errorCode,
            'data'    => $e->errorData,
        ], 403);

       } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $e->errors(),
        ], 422);

       }
         catch (\Exception $e) {
            Log::error('Streaming error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Streaming error: ' . $e->getMessage(),
            ], 500);
        }
    }
}