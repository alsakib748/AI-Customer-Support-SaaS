<?php
// app/Services/Analytics/WidgetAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\WidgetSession;
use App\Models\Tenant\ChatWidget;

class WidgetAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('widget', function () {
            return [
                'summary' => $this->summary(),
                'trend' => $this->trend(),
                'by_widget' => $this->byWidget(),
            ];
        });
    }

    protected function summary(): array
    {
        $query = WidgetSession::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as sessions,
            COUNT(DISTINCT visitor_token) as unique_visitors,
            COUNT(*) FILTER (WHERE current_conversation_id IS NOT NULL) as conversations
        ")->first();

        $sessions = (int) $result->sessions;
        $conversations = (int) $result->conversations;

        return [
            'sessions' => $sessions,
            'unique_visitors' => (int) $result->unique_visitors,
            'conversations' => $conversations,
            'conversion_rate' => $sessions > 0
                ? round(($conversations / $sessions) * 100, 1)
                : 0,
        ];
    }

    protected function trend(): array
    {
        $format = $this->dateTruncFormat();

        $rows = WidgetSession::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$this->interval])
            ->selectRaw("
                COUNT(*) as sessions,
                COUNT(*) FILTER (WHERE current_conversation_id IS NOT NULL) as conversations
            ")
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return [
            'labels' => $rows->pluck('label')->toArray(),
            'datasets' => [
                [
                    'label' => 'Sessions',
                    'values' => $rows->pluck('sessions')->map(fn($v) => (int) $v)->toArray(),
                ],
                [
                    'label' => 'Conversations',
                    'values' => $rows->pluck('conversations')->map(fn($v) => (int) $v)->toArray(),
                ],
            ],
        ];
    }

    protected function byWidget(): array
    {
        $query = WidgetSession::query();
        $this->applyPeriodRange($query);

        $rows = $query->selectRaw("
            widget_id,
            COUNT(*) as sessions,
            COUNT(DISTINCT visitor_token) as visitors,
            COUNT(*) FILTER (WHERE current_conversation_id IS NOT NULL) as conversations
        ")
            ->groupBy('widget_id')
            ->orderByDesc('sessions')
            ->get();

        $widgetIds = $rows->pluck('widget_id')->toArray();
        $widgetNames = ChatWidget::whereIn('id', $widgetIds)
            ->pluck('name', 'id')
            ->toArray();

        return $rows->map(fn($r) => [
            'widget_id' => $r->widget_id,
            'widget_name' => $widgetNames[$r->widget_id] ?? 'Widget #' . $r->widget_id,
            'sessions' => (int) $r->sessions,
            'visitors' => (int) $r->visitors,
            'conversations' => (int) $r->conversations,
            'conversion_rate' => $r->sessions > 0
                ? round(($r->conversations / $r->sessions) * 100, 1)
                : 0,
        ])->toArray();
    }
}
