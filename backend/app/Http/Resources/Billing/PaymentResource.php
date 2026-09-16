<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'invoice_id' => $this->invoice_id,
            'subscription_id' => $this->subscription_id,
            'payment_id' => $this->payment_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted_amount' => $this->formatted_amount,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'is_successful' => $this->is_successful,
            'is_refunded' => $this->is_refunded,
            'can_refund' => $this->can_refund,
            'provider' => $this->provider,
            'payment_method' => $this->payment_method,
            'last_four' => $this->last_four,
            'card_brand' => $this->card_brand,
            'refunded_amount' => $this->refunded_amount,
            'refunded_at' => $this->refunded_at?->toISOString(),
            'refund_reason' => $this->refund_reason,
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
