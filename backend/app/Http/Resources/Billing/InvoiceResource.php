<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'uuid'               => $this->uuid,
            'tenant_id'          => $this->tenant_id,
            'subscription_id'    => $this->subscription_id,
            'invoice_number'     => $this->invoice_number,
            'subtotal'           => (float) $this->subtotal,
            'tax_amount'         => (float) $this->tax_amount,
            'discount_amount'    => (float) $this->discount_amount,
            'total'              => (float) $this->total,
            'currency'           => $this->currency,
            'formatted_total'    => $this->currency . ' ' . number_format((float) $this->total, 2),
            'status'             => $this->status,
            'status_label'       => $this->status_label,
            'status_color'       => $this->status_color,
            'is_paid'            => $this->status === 'paid',
            'is_overdue'         => $this->status === 'open' && $this->due_at && $this->due_at->isPast(),
            'due_at'             => optional($this->due_at)->toISOString(),
            'paid_at'            => optional($this->paid_at)->toISOString(),
            'period_starts_at'   => optional($this->period_starts_at)->toISOString(),
            'period_ends_at'     => optional($this->period_ends_at)->toISOString(),
            'payment_provider'   => $this->payment_provider,
            'provider_invoice_id'=> $this->provider_invoice_id,
            'invoice_pdf_url'    => $this->invoice_pdf_url,
            'hosted_invoice_url' => $this->hosted_invoice_url,
            'line_items'         => $this->line_items ?? [],
            'notes'              => $this->notes,
            'created_at'         => optional($this->created_at)->toISOString(),
            'updated_at'         => optional($this->updated_at)->toISOString(),
        ];
    }
}
