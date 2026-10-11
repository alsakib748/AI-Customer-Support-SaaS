<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 0; padding: 0; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: #111827; }
        .muted { color: #6b7280; }
        .title { font-size: 22px; font-weight: bold; text-align: right; color: #111827; }
        .meta { margin-top: 4px; text-align: right; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items th { text-align: left; background: #f3f4f6; padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; text-transform: uppercase; color: #374151; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        table.totals { width: 40%; margin-left: 60%; margin-top: 12px; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; }
        table.totals tr.grand td { border-top: 2px solid #111827; font-weight: bold; font-size: 14px; padding-top: 8px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-open { background: #dbeafe; color: #1e40af; }
        .badge-void { background: #e5e7eb; color: #374151; }
        .badge-other { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 40px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @php
        $currency = $invoice->currency ?: 'USD';
        $money = fn ($value) => $invoice->currency . ' ' . number_format((float) $value, 2);
        $badgeClass = $invoice->status === 'paid' ? 'badge-paid' : ($invoice->status === 'open' ? 'badge-open' : 'badge-open');
    </style>
    <table class="header">
        <tr>
            <td style="width: 55%;">
                <div class="brand">{{ config('app.name', 'AI Customer Support') }}</div>
                <div class="muted" style="margin-top: 4px;">Billing statement</div>
            </td>
            <td style="width: 45%;">
                <div class="title">INVOICE</div>
                <div class="meta"><strong>{{ $invoice->invoice_number }}</strong></div>
                <div class="meta muted">Status: {{ $invoice->status_label }}</div>
                <div class="meta muted">Issued: {{ optional($invoice->created_at)->format('M d, Y') }}</div>
                @if($invoice->due_at)
                    <div class="meta muted">Due: {{ $invoice->due_at->format('M d, Y') }}</div>
                @endif
                @if($invoice->paid_at)
                    <div class="meta muted">Paid: {{ $invoice->paid_at->format('M d, Y') }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="width: 50%;">
                <div class="section-title">Billed to</div>
                <div><strong>{{ $invoice->tenant?->name ?? 'Tenant' }}</strong></div>
                @if($invoice->tenant?->support_email)
                    <div class="muted">{{ $invoice->tenant->support_email }}</div>
                @endif
            </td>
            <td style="width: 50%;">
                @if($invoice->subscription?->plan)
                    <div class="section-title">Plan</div>
                    <div><strong>{{ $invoice->subscription->plan->name }}</strong></div>
                    <div class="muted">{{ ucfirst($invoice->subscription->billing_cycle ?? 'monthly') }} billing</div>
                @endif
                @if($invoice->period_starts_at || $invoice->period_ends_at)
                    <div class="muted" style="margin-top: 4px;">
                        Period:
                        {{ optional($invoice->period_starts_at)->format('M d, Y') }}
                        &ndash;
                        {{ optional($invoice->period_ends_at)->format('M d, Y') }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right" style="width: 60px;">Qty</th>
                <th class="text-right" style="width: 110px;">Unit price</th>
                <th class="text-right" style="width: 110px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($invoice->line_items ?? []) as $item)
                <tr>
                    <td>{{ $item['description'] ?? 'Item' }}</td>
                    <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Subscription charge</td>
                    <td class="text-right">1</td>
                    <td class="text-right">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">Subtotal</td>
            <td class="text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        @if((float) $invoice->discount_amount > 0)
            <tr>
                <td class="muted">Discount</td>
                <td class="text-right">- {{ $invoice->currency }} {{ number_format((float) $invoice->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if((float) $invoice->tax_amount > 0)
            <tr>
                <td class="muted">Tax</td>
                <td class="text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="text-right">{{ $invoice->formatted_total }}</td>
        </tr>
    </table>

    @if($invoice->notes)
        <div style="margin-top: 28px;">
            <div class="section-title">Notes</div>
            <div class="muted">{{ $invoice->notes }}</div>
        </div>
    @endif

    <div style="margin-top: 40px;" class="muted">
        Thank you for your business.
    </div>
</body>
</html>
