<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class HealthSnapshotResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = (array) $this->resource;

        return [
            'overall'             => $data['overall'],
            'generated_at'        => $data['generated_at'],
            'counts'              => $data['counts'],
            'active_incidents'    => $data['active_incidents'],
            'stale_after_seconds' => $data['stale_after_seconds'],
            'checks'              => $data['checks'],
        ];
    }
}