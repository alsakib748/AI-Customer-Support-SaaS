<?php
// app/Services/Ticket/TicketService.php

namespace App\Services\Ticket;

use App\Models\Tenant\Ticket;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Conversation;
use App\Events\Ticket\TicketCreated;
use App\Events\Ticket\TicketAssigned;
use App\Events\Ticket\TicketResolved;
use App\Events\Ticket\TicketReopened;
use App\Events\Ticket\TicketClosed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TicketService
{
    protected $tenant;

    public function __construct()
    {
        $this->tenant = app('current_tenant');
    }

    /**
     * Generate ticket number
     */
    protected function generateTicketNumber(): string
    {
        $prefix = 'TKT-';
        $year = date('Y');
        $lastTicket = Ticket::withTrashed()
            ->where('ticket_number', 'LIKE', "{$prefix}{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastTicket) {
            $number = (int) substr($lastTicket->ticket_number, -6);
            $newNumber = str_pad($number + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }

        return $prefix . $year . '-' . $newNumber;
    }

    /**
     * Get paginated tickets with filters
     */
    public function getTickets(array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with(['customer', 'conversation']);

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Filter by priority
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        // Filter by source
        if (!empty($filters['source'])) {
            $query->bySource($filters['source']);
        }

        // Filter by assigned user
        if (!empty($filters['assigned_user_id'])) {
            $query->assignedTo($filters['assigned_user_id']);
        }

        // Filter unassigned
        if (!empty($filters['unassigned']) && $filters['unassigned'] === 'true') {
            $query->unassigned();
        }

        // Filter overdue
        if (!empty($filters['overdue']) && $filters['overdue'] === 'true') {
            $query->overdue();
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = [
            'ticket_number',
            'subject',
            'status',
            'priority',
            'created_at',
            'updated_at',
            'due_at',
            'resolved_at',
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    /**
     * Get a single ticket
     */
    public function getTicket(int $id): Ticket
    {
        return Ticket::with(['customer', 'conversation', 'comments'])
            ->findOrFail($id);
    }

    /**
     * Create a new ticket
     */
    public function createTicket(array $data): Ticket
    {
        // Validate customer belongs to tenant
        $customer = Customer::find($data['customer_id']);
        if (!$customer) {
            throw ValidationException::withMessages([
                'customer_id' => ['Customer not found.'],
            ]);
        }

        // Validate conversation if provided
        if (!empty($data['conversation_id'])) {
            $conversation = Conversation::find($data['conversation_id']);
            if (!$conversation) {
                throw ValidationException::withMessages([
                    'conversation_id' => ['Conversation not found.'],
                ]);
            }
        }

        return DB::transaction(function () use ($data) {
            $ticket = Ticket::create([
                'ticket_number' => $this->generateTicketNumber(),
                'customer_id' => $data['customer_id'],
                'conversation_id' => $data['conversation_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'status' => 'open',
                'priority' => $data['priority'] ?? 'normal',
                'type' => $data['type'] ?? 'general',
                'source' => $data['source'] ?? 'manual',
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'created_by_user_id' => auth()->id(),
                'due_at' => $data['due_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            Log::info('Ticket created', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => auth()->id(),
            ]);

            event(new TicketCreated($ticket));

            return $ticket;
        });
    }

    /**
     * Update a ticket
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        Log::info('Ticket updated', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'changes' => $data,
        ]);

        return $ticket->fresh();
    }

    /**
     * Delete a ticket (soft delete)
     */
    public function deleteTicket(Ticket $ticket): bool
    {
        $ticket->delete();

        Log::info('Ticket deleted', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Assign a ticket
     */
    public function assignTicket(Ticket $ticket, int $userId): Ticket
    {
        if (!$ticket->canAssign()) {
            throw ValidationException::withMessages([
                'status' => ['Ticket cannot be assigned in its current state.'],
            ]);
        }

        // Verify user belongs to tenant
        $this->verifyUserInTenant($userId);

        $ticket->assign($userId);

        Log::info('Ticket assigned', [
            'ticket_id' => $ticket->id,
            'assigned_user_id' => $userId,
            'user_id' => auth()->id(),
        ]);

        event(new TicketAssigned($ticket, $userId));

        return $ticket->fresh();
    }

    /**
     * Unassign a ticket
     */
    public function unassignTicket(Ticket $ticket): Ticket
    {
        $ticket->unassign();

        Log::info('Ticket unassigned', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        return $ticket->fresh();
    }

    /**
     * Start a ticket (set to in_progress)
     */
    public function startTicket(Ticket $ticket): Ticket
    {
        if (!$ticket->canTransitionTo('in_progress')) {
            throw ValidationException::withMessages([
                'status' => ['Ticket cannot be started in its current state.'],
            ]);
        }

        $ticket->start();

        Log::info('Ticket started', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        return $ticket->fresh();
    }

    /**
     * Set ticket to pending
     */
    public function setPending(Ticket $ticket): Ticket
    {
        if (!$ticket->canTransitionTo('pending')) {
            throw ValidationException::withMessages([
                'status' => ['Ticket cannot be set to pending in its current state.'],
            ]);
        }

        $ticket->setPending();

        Log::info('Ticket set to pending', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        return $ticket->fresh();
    }

    /**
     * Resolve a ticket
     */
    public function resolveTicket(Ticket $ticket): Ticket
    {
        if (!$ticket->canResolve()) {
            throw ValidationException::withMessages([
                'status' => ['Ticket cannot be resolved in its current state.'],
            ]);
        }

        $ticket->resolve();

        Log::info('Ticket resolved', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        event(new TicketResolved($ticket));

        return $ticket->fresh();
    }

    /**
     * Reopen a ticket
     */
    public function reopenTicket(Ticket $ticket): Ticket
    {
        if (!$ticket->canReopen()) {
            throw ValidationException::withMessages([
                'status' => ['Ticket cannot be reopened in its current state.'],
            ]);
        }

        $ticket->reopen();

        Log::info('Ticket reopened', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        event(new TicketReopened($ticket));

        return $ticket->fresh();
    }

    /**
     * Close a ticket
     */
    public function closeTicket(Ticket $ticket): Ticket
    {
        if (!$ticket->canClose()) {
            throw ValidationException::withMessages([
                'status' => ['Ticket cannot be closed in its current state.'],
            ]);
        }

        $ticket->close();

        Log::info('Ticket closed', [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
        ]);

        event(new TicketClosed($ticket));

        return $ticket->fresh();
    }

    /**
     * Get ticket statistics
     */
    public function getStatistics(): array
    {
        $query = Ticket::query();

        return [
            'total' => $query->count(),
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'unassigned' => (clone $query)->whereNull('assigned_user_id')->count(),
            'overdue' => (clone $query)->overdue()->count(),
            'by_priority' => (clone $query)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'by_type' => (clone $query)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'by_source' => (clone $query)
                ->selectRaw('source, count(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
        ];
    }

    /**
     * Verify user belongs to tenant
     */
    protected function verifyUserInTenant(int $userId): void
    {
        if (!$this->tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['No tenant context found.'],
            ]);
        }

        $exists = DB::connection('central')
            ->table('tenant_user')
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'user' => ['User does not belong to this workspace.'],
            ]);
        }
    }
}
