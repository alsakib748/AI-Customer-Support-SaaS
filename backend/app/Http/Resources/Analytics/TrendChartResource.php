<?php

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrendChartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'labels' => $this->resource['labels'] ?? [],
            'values' => $this->resource['values'] ?? [],
            'datasets' => $this->resource['datasets'] ?? null,
        ];
    }
}
