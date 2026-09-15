<?php
// app/Services/Analytics/TicketAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\Ticket;
use App\Support\Analytics\DurationFormatter;

class TicketAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('tickets', function () {
            return [
                'summary' => $this->summary(),
                'trend' => $this->trend(),
                'status_breakdown' => $this->statusBreakdown(),
                'priority_breakdown' => $this->priorityBreakdown(),
                'type_breakdown' => $this->typeBreakdown(),
                'resolution_time' => $this->resolutionTime(),
            ];
        });
    }

    protected function summary(): array
    {
        $query = Ticket::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'open') as open,
            COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
            COUNT(*) FILTER (WHERE status = 'pending') as pending,
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
            COUNT(*) FILTER (WHERE status = 'closed') as closed,
            COUNT(*) FILTER (WHERE priority = 'urgent') as urgent
        ")->first();

        return [
            'total' => (int) $result->total,
            'open' => (int) $result->open,
            'in_progress' => (int) $result->in_progress,
            'pending' => (int) $result->pending,
            'resolved' => (int) $result->resolved,
            'closed' => (int) $result->closed,
            'urgent' => (int) $result->urgent,
        ];
    }

    protected function trend(): array
    {
        $format = $this->dateTruncFormat();
        $interval = $this->interval;

        $created = Ticket::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$this->interval])
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->keyBy('label');

        $resolved = Ticket::query()
            ->whereBetween('resolved_at', [$this->period->from, $this->period->to])
            ->whereNotNull('resolved_at')
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', resolved_at), '{$format}') as label")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label')
            ->get()
            ->keyBy('label');

        $labels = collect($created->keys())->merge($resolved->keys())->unique()->values();

        return [
            'labels' => $labels->toArray(),
            'datasets' => [
                [
                    'label' => 'Created',
                    'values' => $labels->map(fn($l) => (int) ($created[$l]->count ?? 0))->toArray(),
                ],
                [
                    'label' => 'Resolved',
                    'values' => $labels->map(fn($l) => (int) ($resolved[$l]->count ?? 0))->toArray(),
                ],
            ],
        ];
    }

    protected function statusBreakdown(): array
    {
        $query = Ticket::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) FILTER (WHERE status = 'open') as open,
            COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
            COUNT(*) FILTER (WHERE status = 'pending') as pending,
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
            COUNT(*) FILTER (WHERE status = 'closed') as closed
        ")->first();

        return [
            'labels' => ['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'],
            'values' => [
                (int) $result->open,
                (int) $result->in_progress,
                (int) $result->pending,
                (int) $result->resolved,
                (int) $result->closed,
            ],
        ];
    }

    protected function priorityBreakdown(): array
    {
        $query = Ticket::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) FILTER (WHERE priority = 'low') as low,
            COUNT(*) FILTER (WHERE priority = 'normal') as normal,
            COUNT(*) FILTER (WHERE priority = 'high') as high,
            COUNT(*) FILTER (WHERE priority = 'urgent') as urgent
        ")->first();

        return [
            'labels' => ['Low', 'Normal', 'High', 'Urgent'],
            'values' => [
                (int) $result->low,
                (int) $result->normal,
                (int) $result->high,
                (int) $result->urgent,
            ],
        ];
    }

    protected function typeBreakdown(): array
    {
        $query = Ticket::query();
        $this->applyPeriodRange($query);

        $rows = $query->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $rows->pluck('type')->map(fn($t) => ucfirst($t))->toArray(),
            'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    protected function resolutionTime(): array
    {
        $query = Ticket::query();
        $this->applyPeriodRange($query, 'resolved_at');

        $result = $query
            ->whereNotNull('resolved_at')
            ->selectRaw("
                AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))) as avg_seconds,
                PERCENTILE_CONT(0.5) WITHIN GROUP (
                    ORDER BY EXTRACT(EPOCH FROM (resolved_at - created_at))
                ) as median_seconds
            ")
            ->first();

        $avg = (float) ($result->avg_seconds ?? 0);

        return [
            'average_seconds' => round($avg, 2),
            'average_formatted' => DurationFormatter::format((int) $avg),
            'median_seconds' => round((float) ($result->median_seconds ?? 0), 2),
        ];
    }
}