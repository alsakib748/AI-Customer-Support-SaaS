<?php

namespace App\Http\Controllers\Api\V1\ChatWidget;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatWidget\StoreChatWidgetRequest;
use App\Http\Requests\ChatWidget\UpdateChatWidgetRequest;
use App\Http\Resources\ChatWidget\ChatWidgetCollection;
use App\Http\Resources\ChatWidget\ChatWidgetResource;
use App\Models\Tenant\ChatWidget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatWidgetController extends Controller
{
    /**
     * Get list of chat widgets
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('widgets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view widgets.',
            //     ], 403);
            // }

            //  Super Admin: Return empty or require tenant selection
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: Please use tenant context to view widgets.',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            $widgets = ChatWidget::orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return new ChatWidgetCollection($widgets);

        } catch (\Exception $e) {
            Log::error('Failed to get chat widgets:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve chat widgets.',
            ], 500);
        }
    }

    /**
     * Create a new chat widget
     */
    public function store(StoreChatWidgetRequest $request)
    {
        try {
            //  Check permission
            // if (!auth()->user()->hasPermissionTo('widgets.create')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to create widgets.',
            //     ], 403);
            // }

            // Super Admin cannot create widgets directly
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot create widgets. Please switch to a tenant workspace.',
                    'code' => 'switch_to_tenant',
                ], 400);
            }

            $widget = ChatWidget::create($request->validated());

            Log::info('Widget created', [
                'widget_id' => $widget->id,
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->getRoleNames()->first(),
            ]);

            return (new ChatWidgetResource($widget))
                ->additional([
                    'message' => 'Widget created successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to create chat widget:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create widget.',
            ], 500);
        }
    }

    /**
     * Get a single chat widget
     */
    public function show(ChatWidget $chatWidget)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('widgets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view widgets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have widget context.',
                ], 404);
            }

            return new ChatWidgetResource($chatWidget);

        } catch (\Exception $e) {
            Log::error('Failed to get chat widget:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve widget.',
            ], 500);
        }
    }

    /**
     * Update a chat widget
     */
    public function update(UpdateChatWidgetRequest $request, ChatWidget $chatWidget)
    {
        try {
            // Check permission
            // if (!auth()->user()->hasPermissionTo('widgets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update widgets.',
            //     ], 403);
            // }

            // Super Admin cannot update widgets directly
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot update widgets. Please switch to a tenant workspace.',
                    'code' => 'switch_to_tenant',
                ], 400);
            }

            $chatWidget->update($request->validated());

            Log::info('Widget updated', [
                'widget_id' => $chatWidget->id,
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->getRoleNames()->first(),
            ]);

            return (new ChatWidgetResource($chatWidget))
                ->additional([
                    'message' => 'Widget updated successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to update chat widget:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update widget.',
            ], 500);
        }
    }

    /**
     * Delete a chat widget
     */
    public function destroy(ChatWidget $chatWidget)
    {
        try {
            // Check permission
            if (!auth()->user()->hasPermissionTo('widgets.delete')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete widgets.',
                ], 403);
            }

            // Super Admin cannot delete widgets directly
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete widgets. Please switch to a tenant workspace.',
                    'code' => 'switch_to_tenant',
                ], 400);
            }

            $chatWidget->delete();

            Log::info('Widget deleted', [
                'widget_id' => $chatWidget->id,
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->getRoleNames()->first(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Widget deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete chat widget:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete widget.',
            ], 500);
        }
    }

    /**
     * Enable a chat widget
     */
    public function enable(ChatWidget $chatWidget)
    {
        try {
            // Check permission
            // if (!auth()->user()->hasPermissionTo('widgets.manage')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to enable widgets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot enable widgets directly.',
                ], 400);
            }

            $chatWidget->enable();

            Log::info('Widget enabled', [
                'widget_id' => $chatWidget->id,
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
            ]);

            return (new ChatWidgetResource($chatWidget))
                ->additional([
                    'message' => 'Widget enabled successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to enable chat widget:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to enable widget.',
            ], 500);
        }
    }

    /**
     * Disable a chat widget
     */
    public function disable(ChatWidget $chatWidget)
    {
        try {
            // Check permission
            if (!auth()->user()->hasPermissionTo('widgets.manage')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to disable widgets.',
                ], 403);
            }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot disable widgets directly.',
                ], 400);
            }

            $chatWidget->disable();

            Log::info('Widget disabled', [
                'widget_id' => $chatWidget->id,
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
            ]);

            return (new ChatWidgetResource($chatWidget))
                ->additional([
                    'message' => 'Widget disabled successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to disable chat widget:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disable widget.',
            ], 500);
        }
    }

    /**
     * Regenerate widget public key
     */
    public function regenerateKey(ChatWidget $chatWidget)
    {
        try {
            // Check permission
            // if (!auth()->user()->hasPermissionTo('widgets.manage')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to regenerate keys.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot regenerate keys directly.',
                ], 400);
            }

            $chatWidget->regenerateKey();

            return (new ChatWidgetResource($chatWidget))
                ->additional([
                    'message' => 'Widget key regenerated successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to regenerate widget key:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate widget key.',
            ], 500);
        }
    }

    /**
     * Get installation code
     */
    public function installationCode(ChatWidget $chatWidget)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('widgets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view installation code.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have widget context.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'code' => $chatWidget->getInstallationCode(),
                    'url' => config('app.url') . '/widget/chat.js',
                    'widget_id' => $chatWidget->public_key,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get installation code:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get installation code.',
            ], 500);
        }
    }
}
