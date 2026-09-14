<?php

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'] ?? [],
            'trend' => $this->resource['trend'] ?? null,
            'breakdown' => $this->resource['breakdown'] ?? null,
            'table' => $this->resource['table'] ?? null,
        ];
    }

    public function with($request): array
    {
        return [
            'success' => true,
            'meta' => $this->resource['meta'] ?? [],
        ];
    }
}