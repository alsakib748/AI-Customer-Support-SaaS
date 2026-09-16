<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->type === 'percentage' ? 'Percentage' : 'Fixed Amount',
            'value' => $this->value,
            'formatted_value' => $this->formatted_value,
            'currency' => $this->currency,
            'duration' => $this->duration,
            'duration_months' => $this->duration_months,
            'max_redemptions' => $this->max_redemptions,
            'times_redeemed' => $this->times_redeemed,
            'max_redemptions_per_tenant' => $this->max_redemptions_per_tenant,
            'minimum_amount' => $this->minimum_amount,
            'applicable_plans' => $this->applicable_plans,
            'is_valid' => $this->is_valid,
            'is_expired' => $this->is_expired,
            'is_exhausted' => $this->is_exhausted,
            'starts_at' => $this->starts_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}