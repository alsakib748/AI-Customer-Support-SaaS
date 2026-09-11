<?php

namespace App\Http\Controllers\Api\V1\Widget;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ChatWidget;
use App\Services\ChatWidget\ChatWidgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Facades\Tenancy;

class WidgetController extends Controller
{
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

            // $result = $this->widgetService->bootstrap(
            //     $request->input('widget_id'),
            //     $origin
            // );

            // return response()->json([
            //     'success' => true,
            //     'data' => $result,
            // ]);

            // ✅ Find widget by public key (across all tenants)
            $widget = $this->findWidgetByPublicKey($request->input('widget_id'));

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget not found or inactive.',
                ], 404);
            }

            // ✅ Initialize tenant context
            $this->initializeTenant($widget->tenant);

            // Now everything runs inside the correct tenant database
            $result = $this->widgetService()->bootstrap(
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

            // ✅ Find widget first
            $widget = $this->findWidgetByPublicKey($request->input('widget_id'));

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget not found or inactive.',
                ], 404);
            }

            // ✅ Initialize tenant
            $this->initializeTenant($widget->tenant);

            $session = $this->widgetService()->getOrCreateSession(
                $widget,
                $request->input('visitor_token')
            );

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

            // ✅ Find session and tenant
            $session = $this->findSessionByToken($request->input('session_token'));

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid session.',
                ], 404);
            }

            // ✅ Initialize tenant
            $this->initializeTenant($session->widget->tenant);

            // Get or create customer
            $customer = $this->widgetService()->getOrCreateCustomer($session, [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ]);

            // Send message
            $message = $this->widgetService()->sendMessage(
                $session,
                $request->input('content')
            );

            $session->refresh();
            $conversation = $session->currentConversation;
            $aiConfig = \App\Models\Tenant\AIConfiguration::first();
            $aiEnabled = $aiConfig
                && $aiConfig->enabled
                && $aiConfig->auto_reply_enabled;

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
                        'status_label' => $conversation->status_label,
                    ] : null,
                    'ai_enabled' => (bool) $aiEnabled,
                    'streaming_enabled' => (bool) ($aiConfig?->streaming_enabled ?? false),
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

            $session = $this->findSessionByToken($request->input('session_token'));

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid session.',
                ], 404);
            }

            // ✅ Initialize tenant
            $this->initializeTenant($session->widget->tenant);

            $limit = $request->input('limit', 50);
            $messages = $this->widgetService()->getMessages($session, $limit);

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

            $session = $this->findSessionByToken($request->input('session_token'));

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid session.',
                ], 404);
            }

            // ✅ Initialize tenant
            $this->initializeTenant($session->widget->tenant);

            $conversation = $this->widgetService()->getConversation($session);

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

    /**
     * ✅ Find widget by public key across all tenants
     * This is the CRITICAL part — we search all tenant databases
     */
    protected function findWidgetByPublicKey(string $publicKey): ?ChatWidget
    {
        try {
            // Get all active tenants
            $tenants = \App\Models\Tenant::active()->get();

            foreach ($tenants as $tenant) {
                try {
                    // Initialize tenant
                    $this->initializeTenant($tenant);

                    // Look for widget in this tenant
                    $widget = ChatWidget::where('public_key', $publicKey)
                        ->where('status', 'active')
                        ->first();

                    if ($widget) {
                        // Set tenant relationship for later use
                        $widget->setRelation('tenant', $tenant);

                        Log::info('Widget found', [
                            'widget_id' => $widget->id,
                            'tenant_id' => $tenant->id,
                        ]);

                        return $widget;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to search widget in tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to find widget by public key:', [
                'public_key' => substr($publicKey, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * ✅ Find session by token across all tenants
     */
    protected function findSessionByToken(string $sessionToken): ?\App\Models\Tenant\WidgetSession
    {
        try {
            $tenants = \App\Models\Tenant::active()->get();

            foreach ($tenants as $tenant) {
                try {
                    $this->initializeTenant($tenant);

                    $session = \App\Models\Tenant\WidgetSession::where('session_token', $sessionToken)
                        ->with('widget')
                        ->first();

                    if ($session && $session->widget) {
                        // Set tenant on widget for later use
                        $session->widget->setRelation('tenant', $tenant);
                        return $session;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to find session by token:', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function initializeTenant(\App\Models\Tenant $tenant): void
    {
        Tenancy::initialize($tenant);
        app()->instance('current_tenant', $tenant);
    }

    protected function widgetService(): ChatWidgetService
    {
        return app(ChatWidgetService::class);
    }

}