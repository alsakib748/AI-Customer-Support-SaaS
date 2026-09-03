<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            'initials' => $this->initials,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'tags' => $this->tags ?? [],
            'notes' => $this->notes,
            'default_language' => $this->default_language,
            'timezone' => $this->timezone,
            'total_conversations' => $this->total_conversations,
            'total_tickets' => $this->total_tickets,
            'satisfaction_score' => $this->satisfaction_score,
            'last_contacted_at' => $this->last_contacted_at?->toISOString(),
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
