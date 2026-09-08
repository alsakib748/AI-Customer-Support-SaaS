<?php
// app/Services/Ticket/TicketCommentService.php

namespace App\Services\Ticket;

use App\Models\Tenant\Ticket;
use App\Models\Tenant\TicketComment;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TicketCommentService
{
    /**
     * Get comments for a ticket
     */
    public function getComments(Ticket $ticket, array $filters = [])
    {
        $query = $ticket->comments();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    /**
     * Add a comment to a ticket
     */
    public function addComment(Ticket $ticket, array $data): TicketComment
    {
        $type = $data['type'] ?? 'reply';
        $userId = auth()->id();

        // Check if internal note requires permission
        if ($type === 'internal_note') {
            $user = auth()->user();
            if (!$user || !$user->hasPermissionTo('tickets.update')) {
                throw ValidationException::withMessages([
                    'type' => ['You do not have permission to add internal notes.'],
                ]);
            }
        }

        $comment = $ticket->comments()->create([
            'user_id' => $userId,
            'type' => $type,
            'content' => $data['content'],
            'metadata' => $data['metadata'] ?? null,
        ]);

        Log::info('Ticket comment added', [
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'type' => $type,
            'user_id' => $userId,
        ]);

        return $comment;
    }

    /**
     * Delete a comment
     */
    public function deleteComment(TicketComment $comment): bool
    {
        $comment->delete();

        Log::info('Ticket comment deleted', [
            'comment_id' => $comment->id,
            'ticket_id' => $comment->ticket_id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }
}