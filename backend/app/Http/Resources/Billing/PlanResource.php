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
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'name'                   => $this->name,
            'slug'                   => $this->slug,
            'description'            => $this->description,
            'price_monthly'          => (float) $this->price_monthly,
            'price_yearly'           => (float) $this->price_yearly,
            'formatted_price_monthly'=> $this->formatted_price_monthly,
            'formatted_price_yearly' => $this->formatted_price_yearly,
            'currency'               => $this->currency,
            'trial_days'             => (int) $this->trial_days,
            'features'               => $this->features ?? [],
            'limits'                 => $this->limits ?? [],
            'is_active'              => (bool) $this->is_active,
            'is_default'             => (bool) $this->is_default,
            'is_public'              => (bool) $this->is_public,
            'is_free'                => (float) $this->price_monthly === 0.0 && (float) $this->price_yearly === 0.0,
            'sort_order'             => (int) $this->sort_order,
            'badge'                  => $this->badge,
            'color'                  => $this->color,
            'yearly_savings'         => $this->yearly_savings,
            'yearly_savings_percentage' => $this->yearly_savings_percentage,
            'created_at'             => optional($this->created_at)->toISOString(),
            'updated_at'             => optional($this->updated_at)->toISOString(),
        ];
    }
}
