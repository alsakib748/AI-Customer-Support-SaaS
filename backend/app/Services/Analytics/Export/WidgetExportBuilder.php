<?php
// app/Services/Analytics/Export/WidgetExportBuilder.php

namespace App\Services\Analytics\Export;

use App\Models\Tenant\WidgetSession;
use App\Models\Tenant\ChatWidget;
use App\Support\Analytics\AnalyticsPeriod;

class WidgetExportBuilder
{
    public function build(array $filters): array
    {
        $period = AnalyticsPeriod::fromRequest($filters);

        $rows = WidgetSession::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('
                widget_id,
                COUNT(*) as sessions,
                COUNT(DISTINCT visitor_token) as visitors,
                COUNT(*) FILTER (WHERE current_conversation_id IS NOT NULL) as conversations
            ')
            ->groupBy('widget_id')
            ->orderByDesc('sessions')
            ->get();

        $widgetNames = ChatWidget::whereIn('id', $rows->pluck('widget_id'))
            ->pluck('name', 'id')
            ->toArray();

        $mapped = $rows->map(fn($r) => [
            $r->widget_id,
            $widgetNames[$r->widget_id] ?? 'Widget #' . $r->widget_id,
            (int) $r->sessions,
            (int) $r->visitors,
            (int) $r->conversations,
            $r->sessions > 0
            ? round(($r->conversations / $r->sessions) * 100, 2) . '%'
            : '0%',
        ]);

        $header = [
            'Widget ID',
            'Widget Name',
            'Sessions',
            'Unique Visitors',
            'Conversations',
            'Conversion Rate',
        ];

        return [$header, $mapped];
    }
}