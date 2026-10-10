<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class HealthCheckResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'check_key'   => $this->check_key,
            'status'      => $this->status,
            'summary'     => $this->summary,
            'metadata'    => $this->metadata ?? [],
            'duration_ms' => (int) $this->duration_ms,
            'checked_at'  => $this->checked_at?->toIso8601String(),
        ];
    }
}