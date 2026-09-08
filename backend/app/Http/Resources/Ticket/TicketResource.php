<?php

namespace App\Http\Resources\Ticket;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'priority_color' => $this->priority_color,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'source' => $this->source,
            'source_label' => $this->source_label,
            'assigned_user_id' => $this->assigned_user_id,
            'created_by_user_id' => $this->created_by_user_id,
            'due_at' => $this->due_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'is_open' => $this->is_open,
            'is_in_progress' => $this->is_in_progress,
            'is_pending' => $this->is_pending,
            'is_resolved' => $this->is_resolved,
            'is_closed' => $this->is_closed,
            'is_overdue' => $this->is_overdue,
            'actions' => $this->getStatusActions(),
            'customer' => [
                'id' => $this->customer?->id,
                'first_name' => $this->customer?->first_name,
                'last_name' => $this->customer?->last_name,
                'full_name' => $this->customer?->full_name,
                'email' => $this->customer?->email,
                'phone' => $this->customer?->phone,
                'company_name' => $this->customer?->company_name,
            ],
            'conversation' => $this->conversation ? [
                'id' => $this->conversation->id,
                'subject' => $this->conversation->subject,
                'status' => $this->conversation->status,
            ] : null,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}