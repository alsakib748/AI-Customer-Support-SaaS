<?php

namespace App\Http\Controllers\Api\V1\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreInternalNoteRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\Message\MessageCollection;
use App\Http\Resources\Message\MessageResource;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Services\Message\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    protected MessageService $service;

    public function __construct(MessageService $service)
    {
        $this->service = $service;
    }

    /**
     * Get messages for a conversation
     */
    public function index(Request $request, Conversation $conversation)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view messages.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: No tenant context required.',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 30,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            $filters = $request->only([
                'message_type',
                'sender_type',
                'per_page',
            ]);

            $messages = $this->service->getMessages($conversation, $filters);

            return new MessageCollection($messages);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to get messages:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages.',
            ], 500);
        }
    }

    /**
     * Send a message as an agent
     */
    public function store(StoreMessageRequest $request, Conversation $conversation)
    {
        try {
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot send messages without tenant context.',
                ], 400);
            }

            $message = $this->service->createAgentMessage(
                $conversation,
                $request->validated()
            );

            return (new MessageResource($message))
                ->additional([
                    'message' => 'Message sent successfully',
                ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to send message:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message.',
            ], 500);
        }
    }

    /**
     * Add an internal note
     */
    public function addNote(StoreInternalNoteRequest $request, Conversation $conversation)
    {
        try {
            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot add notes without tenant context.',
                ], 400);
            }

            $message = $this->service->createInternalNote(
                $conversation,
                $request->validated()
            );

            return (new MessageResource($message))
                ->additional([
                    'message' => 'Note added successfully',
                ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to add note:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add note.',
            ], 500);
        }
    }

    /**
     * Get a single message
     */
    public function show(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view messages.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have message context.',
                ], 404);
            }

            $message = Message::findOrFail($id);
            $message = $this->service->getMessage($message);

            return new MessageResource($message);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found.',
            ], 404);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
                'errors' => $e->errors(),
            ], 403);

        } catch (\Exception $e) {
            Log::error('Failed to get message:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message.',
            ], 500);
        }
    }

    /**
     * Delete a message
     */
    public function destroy(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('conversations.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete messages.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete messages without tenant context.',
                ], 400);
            }

            $message = Message::findOrFail($id);
            $this->service->deleteMessage($message);

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found.',
            ], 404);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete message.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to delete message:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message.',
            ], 500);
        }
    }
}