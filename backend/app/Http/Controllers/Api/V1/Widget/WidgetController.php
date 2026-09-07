<?php

namespace App\Http\Controllers\Api\V1\Widget;

use App\Http\Controllers\Controller;
use App\Services\ChatWidget\ChatWidgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WidgetController extends Controller
{
    protected ChatWidgetService $widgetService;

    public function __construct(ChatWidgetService $widgetService)
    {
        $this->widgetService = $widgetService;
    }

    /**
     * Bootstrap the widget
     */
    public function bootstrap(Request $request)
    {
        try {
            $request->validate([
                'widget_id' => ['required', 'string'],
                'origin' => ['nullable', 'url'],
            ]);

            $origin = $request->input('origin') ?? $request->headers->get('origin');

            $result = $this->widgetService->bootstrap(
                $request->input('widget_id'),
                $origin
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Widget bootstrap failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize widget.',
            ], 500);
        }
    }

    /**
     * Create or get widget session
     */
    public function session(Request $request)
    {
        try {
            $request->validate([
                'widget_id' => ['required', 'string'],
                'visitor_token' => ['nullable', 'string'],
                'metadata' => ['nullable', 'array'],
            ]);

            $widget = \App\Models\Tenant\ChatWidget::active()
                ->byPublicKey($request->input('widget_id'))
                ->first();

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget not found or inactive.',
                ], 404);
            }

            $session = $this->widgetService->getOrCreateSession(
                $widget,
                $request->input('visitor_token')
            );

            // Update metadata
            if ($request->has('metadata')) {
                $session->update([
                    'metadata' => array_merge(
                        $session->metadata ?? [],
                        $request->input('metadata')
                    ),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'session_token' => $session->session_token,
                    'visitor_token' => $session->visitor_token,
                    'has_customer' => $session->has_customer,
                    'has_conversation' => $session->has_conversation,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Widget session failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create session.',
            ], 500);
        }
    }

    /**
     * Send a message from the widget
     */
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'session_token' => ['required', 'string'],
                'content' => ['required', 'string', 'max:10000'],
                'name' => ['nullable', 'string', 'max:100'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
            ]);

            // Validate session
            $session = $this->widgetService->validateSession(
                $request->input('session_token')
            );

            // Get or create customer with provided info
            $customer = $this->widgetService->getOrCreateCustomer($session, [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ]);

            // Send message
            $message = $this->widgetService->sendMessage(
                $session,
                $request->input('content')
            );

            // Get updated conversation info
            $conversation = $session->currentConversation;

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully 🎉',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'content' => $message->content,
                        'created_at' => $message->created_at->toISOString(),
                    ],
                    'conversation' => $conversation ? [
                        'id' => $conversation->id,
                        'status' => $conversation->status,
                    ] : null,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Widget send message failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get messages for the widget
     */
    public function getMessages(Request $request)
    {
        try {
            $request->validate([
                'session_token' => ['required', 'string'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $session = $this->widgetService->validateSession(
                $request->input('session_token')
            );

            $limit = $request->input('limit', 50);
            $messages = $this->widgetService->getMessages($session, $limit);

            return response()->json([
                'success' => true,
                'data' => $messages,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Widget get messages failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get messages.',
            ], 500);
        }
    }

    /**
     * Get conversation for the widget
     */
    public function getConversation(Request $request)
    {
        try {
            $request->validate([
                'session_token' => ['required', 'string'],
            ]);

            $session = $this->widgetService->validateSession(
                $request->input('session_token')
            );

            $conversation = $this->widgetService->getConversation($session);

            if (!$conversation) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active conversation.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $conversation->id,
                    'subject' => $conversation->subject,
                    'status' => $conversation->status,
                    'status_label' => $conversation->status_label,
                    'status_color' => $conversation->status_color,
                    'created_at' => $conversation->created_at->toISOString(),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Widget get conversation failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversation.',
            ], 500);
        }
    }
}
