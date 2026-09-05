<?php

namespace App\Http\Resources\Conversation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'customer' => [
                'id' => $this->customer?->id,
                'first_name' => $this->customer?->first_name,
                'last_name' => $this->customer?->last_name,
                'full_name' => $this->customer?->full_name,
                'email' => $this->customer?->email,
                'phone' => $this->customer?->phone,
                'company_name' => $this->customer?->company_name,
            ],
            'subject' => $this->subject,
            'channel' => $this->channel,
            'channel_label' => $this->channel_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'priority_color' => $this->priority_color,
            'assigned_user_id' => $this->assigned_user_id,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'time_ago' => $this->time_ago,
            'messages_count' => $this->whenCounted('messages', 0),
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
