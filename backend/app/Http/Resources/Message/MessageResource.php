<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'conversation_id' => $this->conversation_id,

            'sender' => [
                'type' => $this->sender_type,
                'id' => $this->sender_id,
                'name' => $this->sender_name,
                'avatar_url' => $this->sender_avatar,
                'is_customer' => $this->is_customer,
                'is_agent' => $this->is_agent,
                'is_ai' => $this->is_ai,
                'is_system' => $this->is_system,
            ],

            'message_type' => $this->message_type,
            'content' => $this->content,
            'is_internal' => $this->is_internal,
            'is_internal_note' => $this->is_internal_note,
            'is_text_message' => $this->is_text_message,

            'metadata' => $this->metadata,

            'time_ago' => $this->time_ago,
            'formatted_date' => $this->formatted_date,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}