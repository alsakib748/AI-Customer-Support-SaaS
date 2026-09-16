<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'formatted_price_monthly' => $this->formatted_price_monthly,
            'formatted_price_yearly' => $this->formatted_price_yearly,
            'currency' => $this->currency,
            'trial_days' => $this->trial_days,
            'features' => $this->features,
            'limits' => $this->limits,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_public' => $this->is_public,
            'is_free' => $this->isFree(),
            'sort_order' => $this->sort_order,
            'badge' => $this->badge,
            'color' => $this->color,
            'yearly_savings' => $this->yearly_savings,
            'yearly_savings_percentage' => $this->yearly_savings_percentage,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
