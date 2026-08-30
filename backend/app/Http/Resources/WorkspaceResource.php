<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'subdomain' => $this->subdomain,
            'domain' => $this->domain,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'industry' => $this->industry,
            'timezone' => $this->timezone ?? 'UTC',
            'default_language' => $this->default_language ?? 'en',
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'business_hours' => $this->business_hours,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
