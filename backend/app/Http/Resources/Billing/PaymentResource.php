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
            'id'                       => $this->id,
            'uuid'                     => $this->uuid,
            'tenant_id'                => $this->tenant_id,
            'invoice_id'               => $this->invoice_id,
            'subscription_id'          => $this->subscription_id,
            'payment_id'               => $this->payment_id,
            'amount'                   => (float) $this->amount,
            'currency'                 => $this->currency,
            'formatted_amount'         => $this->currency . ' ' . number_format((float) $this->amount, 2),
            'status'                   => $this->status,
            'status_label'             => $this->status_label,
            'status_color'             => $this->status_color,
            'provider'                 => $this->provider,
            'provider_payment_id'      => $this->provider_payment_id,
            'provider_invoice_id'      => $this->provider_invoice_id,
            'provider_subscription_id' => $this->provider_subscription_id,
            'payment_method'           => $this->payment_method,
            'last_four'                => $this->last_four,
            'card_brand'               => $this->card_brand,
            'refunded_amount'          => (float) ($this->refunded_amount ?? 0),
            'refunded_at'              => optional($this->refunded_at)->toISOString(),
            'refund_reason'            => $this->refund_reason,
            'failure_reason'           => $this->failure_reason,
            'paid_at'                  => optional($this->paid_at)->toISOString(),
            'metadata'                 => $this->metadata,
            'created_at'               => optional($this->created_at)->toISOString(),
            'updated_at'               => optional($this->updated_at)->toISOString(),
        ];
    }
}
