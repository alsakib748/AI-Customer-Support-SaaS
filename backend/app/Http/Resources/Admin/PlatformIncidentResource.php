<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformIncidentResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'id'         => $this->id,
            'fingerprint'=> $this->fingerprint,
            'title'      => $this->title,
            'summary'    => $this->summary,
            'severity'   => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'status'     => $this->status->value,
            'status_label'   => $this->status->label(),
            'source'     => $this->source,
            'started_at' => $this->started_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'metadata'   => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('acknowledgedBy')) {
            $data['acknowledged_by_name'] = $this->acknowledgedBy?->name ?? null;
        }

        if ($this->relationLoaded('resolvedBy')) {
            $data['resolved_by_name'] = $this->resolvedBy?->name ?? null;
        }

        if ($this->relationLoaded('events')) {
            $data['events'] = $this->events
                ->sortByDesc('id')
                ->values()
                ->map(fn ($event) => [
                    'id'         => $event->id,
                    'event'      => $event->event,
                    'severity'   => $event->severity?->value,
                    'summary'    => $event->summary,
                    'actor_id'   => $event->actor_id,
                    'actor_name' => $event->relationLoaded('actor') ? ($event->actor?->name ?? 'System') : null,
                    'created_at' => $event->created_at?->toIso8601String(),
                ]);
        }

        return $data;
    }
}