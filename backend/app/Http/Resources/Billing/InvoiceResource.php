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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'invoice_number' => $this->invoice_number,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'formatted_total' => $this->formatted_total,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'is_paid' => $this->is_paid,
            'is_overdue' => $this->is_overdue,
            'due_at' => $this->due_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_method' => $this->payment_method,
            'payment_provider' => $this->payment_provider,
            'period_starts_at' => $this->period_starts_at?->toISOString(),
            'period_ends_at' => $this->period_ends_at?->toISOString(),
            'invoice_pdf_url' => $this->invoice_pdf_url,
            'hosted_invoice_url' => $this->hosted_invoice_url,
            'line_items' => $this->line_items,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}