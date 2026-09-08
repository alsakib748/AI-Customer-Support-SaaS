<?php

namespace App\Http\Controllers\Api\V1\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\AssignTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Http\Resources\Ticket\TicketCollection;
use App\Http\Resources\Ticket\TicketResource;
use App\Services\Ticket\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    protected TicketService $service;

    public function __construct(TicketService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of tickets
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view tickets.',
            //     ], 403);
            // }

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
                'type',
                'source',
                'assigned_user_id',
                'unassigned',
                'overdue',
                'date_from',
                'date_to',
                'sort',
                'direction',
                'per_page',
            ]);

            $tickets = $this->service->getTickets($filters);

            return new TicketCollection($tickets);

        } catch (\Exception $e) {
            Log::error('Failed to get tickets:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets.',
            ], 500);
        }
    }

    /**
     * Create a new ticket
     */
    public function store(StoreTicketRequest $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.create')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to create tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot create tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->createTicket($request->validated());

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket created successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer or Conversation not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to create ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket.',
            ], 500);
        }
    }

    /**
     * Get a single ticket
     */
    public function show(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have ticket context.',
                ], 404);
            }

            $ticket = $this->service->getTicket($id);

            return new TicketResource($ticket);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket.',
            ], 500);
        }
    }

    /**
     * Update a ticket
     */
    public function update(UpdateTicketRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot update tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->updateTicket($ticket, $request->validated());

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket updated successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket.',
            ], 500);
        }
    }

    /**
     * Delete a ticket
     */
    public function destroy(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $this->service->deleteTicket($ticket);

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket.',
            ], 500);
        }
    }

    /**
     * Assign a ticket
     */
    public function assign(AssignTicketRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.assign')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to assign tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot assign tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->assignTicket($ticket, $request->user_id);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket assigned successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign ticket.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to assign ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket.',
            ], 500);
        }
    }

    /**
     * Unassign a ticket
     */
    public function unassign(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.assign')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to unassign tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot unassign tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->unassignTicket($ticket);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket unassigned successfully.',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to unassign ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign ticket.',
            ], 500);
        }
    }

    /**
     * Start a ticket (set to in_progress)
     */
    public function start(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to start tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot start tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->startTicket($ticket);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket started successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot start ticket.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to start ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start ticket.',
            ], 500);
        }
    }

    /**
     * Set ticket to pending
     */
    public function pending(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to set tickets to pending.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot set tickets to pending without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->setPending($ticket);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket set to pending',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set ticket to pending.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to set ticket to pending:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set ticket to pending.',
            ], 500);
        }
    }

    /**
     * Resolve a ticket
     */
    public function resolve(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to resolve tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot resolve tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->resolveTicket($ticket);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket resolved successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot resolve ticket.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to resolve ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve ticket.',
            ], 500);
        }
    }

    /**
     * Reopen a ticket
     */
    public function reopen(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to reopen tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot reopen tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->reopenTicket($ticket);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket reopened successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reopen ticket.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to reopen ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reopen ticket.',
            ], 500);
        }
    }

    /**
     * Close a ticket
     */
    public function close(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to close tickets.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot close tickets without tenant context.',
                ], 400);
            }

            $ticket = $this->service->getTicket($id);
            $ticket = $this->service->closeTicket($ticket);

            return (new TicketResource($ticket))
                ->additional([
                    'message' => 'Ticket closed successfully',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot close ticket.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to close ticket:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to close ticket.',
            ], 500);
        }
    }

    /**
     * Get ticket statistics
     */
    public function statistics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('tickets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view ticket statistics.',
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
            Log::error('Failed to get ticket statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket statistics.',
            ], 500);
        }
    }

    protected function getEmptyStatistics(): array
    {
        return [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'pending' => 0,
            'resolved' => 0,
            'closed' => 0,
            'unassigned' => 0,
            'overdue' => 0,
            'by_priority' => [],
            'by_type' => [],
            'by_source' => [],
        ];
    }
}