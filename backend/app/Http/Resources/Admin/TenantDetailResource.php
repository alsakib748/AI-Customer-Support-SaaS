<?php

namespace App\Http\Resources\Admin;

use App\Services\Tenant\TenantStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantDetailResource extends JsonResource
{
    public function __construct(
        $resource,
        protected ?array $statistics = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * Transform the tenant detail resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new TenantResource($this->resource))->toArray($request);

        $overview = $this->statistics['overview'] ?? null;
        $usage    = $this->statistics['usage'] ?? null;

        return array_merge($base, [
            'business_hours' => $this->business_hours,
            'metadata'       => $this->metadata,
            'overview'       => $overview,
            'usage'          => $usage,
        ]);
    }
}