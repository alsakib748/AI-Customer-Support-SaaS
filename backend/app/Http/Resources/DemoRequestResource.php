<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemoRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'company' => $this->company,
            'team_size' => $this->team_size,
            'interests' => $this->interests,
            'message' => $this->message,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'meeting_link' => $this->meeting_link,
            'notes' => $this->notes,
            'handled_by' => $this->handled_by,
            'handled_at' => $this->handled_at?->toISOString(),
            'handler' => $this->whenLoaded('handler', function () {
                return [
                    'id' => $this->handler?->id,
                    'name' => $this->handler?->name,
                    'email' => $this->handler?->email,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
