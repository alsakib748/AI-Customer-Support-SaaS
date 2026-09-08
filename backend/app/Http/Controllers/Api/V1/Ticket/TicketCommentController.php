<?php

namespace App\Http\Controllers\Api\V1\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\StoreTicketCommentRequest;
use App\Http\Resources\Ticket\TicketCommentResource;
use App\Models\Tenant\Ticket;
use App\Services\Ticket\TicketCommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketCommentController extends Controller
{
    protected TicketCommentService $service;

    public function __construct(TicketCommentService $service)
    {
        $this->service = $service;
    }

    /**
     * Get comments for a ticket
     */
    public function index(Request $request, Ticket $ticket)
    {
        try {
            if (!auth()->user()->hasPermissionTo('tickets.view')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view ticket comments.',
                ], 403);
            }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: No tenant context required.',
                    'data' => [],
                ]);
            }

            $filters = $request->only(['type']);
            $comments = $this->service->getComments($ticket, $filters);

            return response()->json([
                'success' => true,
                'data' => TicketCommentResource::collection($comments),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get ticket comments:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket comments.',
            ], 500);
        }
    }

    /**
     * Add a comment to a ticket
     */
    public function store(StoreTicketCommentRequest $request, Ticket $ticket)
    {
        try {
            if (!auth()->user()->hasPermissionTo('tickets.update')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to add comments.',
                ], 403);
            }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot add comments without tenant context.',
                ], 400);
            }

            $comment = $this->service->addComment($ticket, $request->validated());

            return (new TicketCommentResource($comment))
                ->additional([
                    'message' => 'Comment added successfully',
                ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to add ticket comment:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment.',
            ], 500);
        }
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, $ticketId, $commentId)
    {
        try {
            if (!auth()->user()->hasPermissionTo('tickets.delete')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete comments.',
                ], 403);
            }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete comments without tenant context.',
                ], 400);
            }

            $comment = \App\Models\Tenant\TicketComment::findOrFail($commentId);

            // Verify comment belongs to ticket
            if ($comment->ticket_id != $ticketId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment does not belong to this ticket.',
                ], 404);
            }

            $this->service->deleteComment($comment);

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete ticket comment:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment.',
            ], 500);
        }
    }
}