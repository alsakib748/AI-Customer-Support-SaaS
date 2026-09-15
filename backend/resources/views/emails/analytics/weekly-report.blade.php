{{-- resources/views/emails/analytics/weekly-report.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Weekly Analytics Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
            color: #111827;
            max-width: 640px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            background: #4F46E5;
            color: white;
            padding: 24px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }

        .content {
            background: #F9FAFB;
            padding: 32px 24px;
            border-radius: 0 0 12px 12px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin: 24px 0;
        }

        .kpi {
            background: white;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
        }

        .kpi-label {
            font-size: 12px;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin-top: 4px;
        }

        .change-up {
            color: #10B981;
            font-size: 13px;
            margin-top: 2px;
        }

        .change-down {
            color: #EF4444;
            font-size: 13px;
            margin-top: 2px;
        }

        .cta {
            display: inline-block;
            background: #4F46E5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 16px;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #6B7280;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin:0;font-size:22px;">Weekly Analytics Report</h1>
        <p style="margin:8px 0 0 0;opacity:0.9;">{{ $tenantName }} — {{ $periodLabel }}</p>
    </div>

    <div class="content">
        <p>Hi there,</p>
        <p>Here's a snapshot of your workspace performance for the past week.</p>

        @php
            $summary = $overview['summary'] ?? [];
            $fmt = fn($m) => $m['current'] ?? $m['value'] ?? 0;
            $chg = fn($m) => $m['change_percentage'] ?? null;
            $dir = fn($m) => ($m['is_positive'] ?? true) ? 'change-up' : 'change-down';
        @endphp

        <div class="grid">
            <div class="kpi">
                <div class="kpi-label">Conversations</div>
                <div class="kpi-value">{{ number_format($fmt($summary['total_conversations'] ?? [])) }}</div>
                @if($chg($summary['total_conversations'] ?? []) !== null)
                    <div class="{{ $dir($summary['total_conversations'] ?? []) }}">
                        {{ $chg($summary['total_conversations'] ?? []) > 0 ? '↑' : '↓' }}
                        {{ abs($chg($summary['total_conversations'] ?? [])) }}%
                    </div>
                @endif
            </div>

            <div class="kpi">
                <div class="kpi-label">Resolved</div>
                <div class="kpi-value">{{ number_format($fmt($summary['resolved_conversations'] ?? [])) }}</div>
                @if($chg($summary['resolved_conversations'] ?? []) !== null)
                    <div class="{{ $dir($summary['resolved_conversations'] ?? []) }}">
                        {{ $chg($summary['resolved_conversations'] ?? []) > 0 ? '↑' : '↓' }}
                        {{ abs($chg($summary['resolved_conversations'] ?? [])) }}%
                    </div>
                @endif
            </div>

            <div class="kpi">
                <div class="kpi-label">New Customers</div>
                <div class="kpi-value">{{ number_format($fmt($summary['new_customers'] ?? [])) }}</div>
                @if($chg($summary['new_customers'] ?? []) !== null)
                    <div class="{{ $dir($summary['new_customers'] ?? []) }}">
                        {{ $chg($summary['new_customers'] ?? []) > 0 ? '↑' : '↓' }}
                        {{ abs($chg($summary['new_customers'] ?? [])) }}%
                    </div>
                @endif
            </div>

            <div class="kpi">
                <div class="kpi-label">Open Tickets</div>
                <div class="kpi-value">{{ number_format($fmt($summary['open_tickets'] ?? [])) }}</div>
            </div>

            <div class="kpi">
                <div class="kpi-label">Avg Response</div>
                <div class="kpi-value" style="font-size:18px;">
                    {{ $summary['formatted']['avg_response_time'] ?? '—' }}
                </div>
            </div>

            <div class="kpi">
                <div class="kpi-label">Avg Resolution</div>
                <div class="kpi-value" style="font-size:18px;">
                    {{ $summary['formatted']['avg_resolution_time'] ?? '—' }}
                </div>
            </div>
        </div>

        <p style="text-align:center;">
            <a href="{{ config('app.frontend_url', config('app.url')) }}/analytics/overview" class="cta">
                View Full Dashboard →
            </a>
        </p>
    </div>

    <div class="footer">
        <p>Sent from {{ config('app.name') }} — Analytics & Reporting</p>
        <p>You can disable weekly reports in your workspace settings.</p>
    </div>
</body>

</html>