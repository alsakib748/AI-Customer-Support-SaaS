<?php

namespace App\Http\Controllers\Api\V1\Conversation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\AssignConversationRequest;
use App\Http\Requests\Conversation\StoreConversactionRequest;
use App\Http\Requests\Conversation\UpdateConversactionRequest;
use App\Http\Resources\Conversation\ConversationCollection;
use App\Http\Resources\Conversation\ConversationResource;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    protected ConversationService $service;

    public function __construct(ConversationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of conversations
     */
    public function index(Request $request)
    {
        try {
            // Check permission
            // if (!auth()->user()->hasPermissionTo('conversations.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view conversations.',
            //     ], 403);
            // }

            // Check if Super Admin
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: No tenant context required.',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            $filters = $request->only([
                'search',
                'status',
                'priority',
                'channel',
                'assigned_user_id',
                'unassigned',
                'sort',
                'direction',
                'per_page',
            ]);

            $conversations = $this->service->list($filters);

            return new ConversationCollection($conversations);

        } catch (\Exception $e) {
            Log::error('Failed to get conversations:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations.',
            ], 500);
        }
    }

    /**
     * Create a new conversation
     */
    public function store(StoreConversactionRequest $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.create')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to create conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot create conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->create($request->validated());

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation created successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to create conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation.',
            ], 500);
        }
    }

    /**
     * Get a single conversation
     */
    public function show(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have conversation context.',
                ], 404);
            }

            $conversation = $this->service->getConversation($id);

            return new ConversationResource($conversation);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation.',
            ], 500);
        }
    }

    /**
     * Update a conversation
     */
    public function update(UpdateConversactionRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot update conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $conversation = $this->service->update($conversation, $request->validated());

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation updated successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update conversation.',
            ], 500);
        }
    }

    /**
     * Delete a conversation (soft delete)
     */
    public function destroy(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $this->service->delete($conversation);

            return response()->json([
                'success' => true,
                'message' => 'Conversation archived successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation.',
            ], 500);
        }
    }

    /**
     * Resolve a conversation
     */
    public function resolve(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to resolve conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot resolve conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $conversation = $this->service->resolve($conversation);

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation resolved successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to resolve conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reopen a conversation
     */
    public function reopen(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to reopen conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot reopen conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $conversation = $this->service->reopen($conversation);

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation reopened successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to reopen conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Close a conversation
     */
    public function close(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to close conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot close conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $conversation = $this->service->close($conversation);

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation closed successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to close conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Assign a conversation to a user
     */
    public function assign(AssignConversationRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.assign')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to assign conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot assign conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $conversation = $this->service->assign($conversation, $request->user_id);

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation assigned successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to assign conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unassign a conversation
     */
    public function unassign(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.assign')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to unassign conversations.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot unassign conversations without tenant context.',
                ], 400);
            }

            $conversation = $this->service->getConversation($id);
            $conversation = $this->service->unassign($conversation);

            return (new ConversationResource($conversation))
                ->additional([
                    'message' => 'Conversation unassigned successfully.',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to unassign conversation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign conversation.',
            ], 500);
        }
    }

    /**
     * Get conversation statistics
     */
    public function statistics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view statistics.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: No tenant context required.',
                    'data' => $this->getEmptyStatistics(),
                ]);
            }

            $statistics = $this->service->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get conversation statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation statistics.',
            ], 500);
        }
    }

    /**
     * Get empty statistics for Super Admin
     */
    protected function getEmptyStatistics(): array
    {
        return [
            'total' => 0,
            'open' => 0,
            'pending' => 0,
            'resolved' => 0,
            'closed' => 0,
            'unassigned' => 0,
            'urgent' => 0,
            'high' => 0,
            'by_channel' => [],
        ];
    }
}