<?php
// app/Services/Analytics/ConversationAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\Conversation;
use App\Support\Analytics\DurationFormatter;

class ConversationAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('conversations', function () {
            return [
                'summary' => $this->summary(),
                'trend' => $this->trend(),
                'status_breakdown' => $this->statusBreakdown(),
                'priority_breakdown' => $this->priorityBreakdown(),
                'channel_breakdown' => $this->channelBreakdown(),
                'response_time' => $this->responseTimeStats(),
                'resolution_time' => $this->resolutionTimeStats(),
            ];
        });
    }

    protected function summary(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'open') as open,
            COUNT(*) FILTER (WHERE status = 'pending') as pending,
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
            COUNT(*) FILTER (WHERE status = 'closed') as closed,
            COUNT(*) FILTER (WHERE assigned_user_id IS NULL) as unassigned
        ")->first();

        return [
            'total' => (int) $result->total,
            'open' => (int) $result->open,
            'pending' => (int) $result->pending,
            'resolved' => (int) $result->resolved,
            'closed' => (int) $result->closed,
            'escalated' => 0,
            'unassigned' => (int) $result->unassigned,
        ];
    }

    protected function trend(): array
    {
        $format = $this->dateTruncFormat();

        // Created vs resolved in same period
        $created = Conversation::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$this->interval])
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->keyBy('label');

        $resolved = Conversation::query()
            ->whereBetween('resolved_at', [$this->period->from, $this->period->to])
            ->whereNotNull('resolved_at')
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', resolved_at), '{$format}') as label")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label')
            ->get()
            ->keyBy('label');

        $allLabels = collect($created->keys())
            ->merge($resolved->keys())
            ->unique()
            ->values();

        return [
            'labels' => $allLabels->toArray(),
            'datasets' => [
                [
                    'label' => 'Created',
                    'values' => $allLabels->map(fn($l) => (int) ($created[$l]->count ?? 0))->toArray(),
                ],
                [
                    'label' => 'Resolved',
                    'values' => $allLabels->map(fn($l) => (int) ($resolved[$l]->count ?? 0))->toArray(),
                ],
            ],
        ];
    }

    protected function statusBreakdown(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) FILTER (WHERE status = 'open') as open,
            COUNT(*) FILTER (WHERE status = 'pending') as pending,
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
            COUNT(*) FILTER (WHERE status = 'closed') as closed
        ")->first();

        return [
            'labels' => ['Open', 'Pending', 'Resolved', 'Closed'],
            'values' => [
                (int) $result->open,
                (int) $result->pending,
                (int) $result->resolved,
                (int) $result->closed,
            ],
        ];
    }

    protected function priorityBreakdown(): array
    {
        $query = Conversation::query();
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

    protected function channelBreakdown(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query);

        $rows = $query->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $rows->pluck('channel')->map(fn($c) => ucfirst($c))->toArray(),
            'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Response time statistics using SQL aggregation.
     * First response = first agent/AI message after first customer message.
     */
    protected function responseTimeStats(): array
    {
        // Get first customer message and first agent/AI response per conversation
        $result = $this->connection()
            ? \DB::connection($this->connection())->selectOne("
                WITH first_customer AS (
                    SELECT conversation_id, MIN(created_at) AS first_at
                    FROM messages
                    WHERE sender_type = 'customer'
                      AND created_at BETWEEN ? AND ?
                    GROUP BY conversation_id
                ),
                first_response AS (
                    SELECT m.conversation_id, MIN(m.created_at) AS response_at
                    FROM messages m
                    INNER JOIN first_customer fc ON fc.conversation_id = m.conversation_id
                    WHERE m.sender_type IN ('agent', 'ai')
                      AND m.created_at > fc.first_at
                    GROUP BY m.conversation_id
                )
                SELECT
                    AVG(EXTRACT(EPOCH FROM (fr.response_at - fc.first_at))) as avg_seconds,
                    PERCENTILE_CONT(0.5) WITHIN GROUP (
                        ORDER BY EXTRACT(EPOCH FROM (fr.response_at - fc.first_at))
                    ) as median_seconds,
                    PERCENTILE_CONT(0.9) WITHIN GROUP (
                        ORDER BY EXTRACT(EPOCH FROM (fr.response_at - fc.first_at))
                    ) as p90_seconds,
                    MIN(EXTRACT(EPOCH FROM (fr.response_at - fc.first_at))) as min_seconds,
                    MAX(EXTRACT(EPOCH FROM (fr.response_at - fc.first_at))) as max_seconds
                FROM first_customer fc
                INNER JOIN first_response fr ON fr.conversation_id = fc.conversation_id
            ", [$this->period->from, $this->period->to])
            : null;

        $avg = (float) ($result->avg_seconds ?? 0);

        return [
            'average_seconds' => round($avg, 2),
            'average_formatted' => DurationFormatter::format((int) $avg),
            'median_seconds' => round((float) ($result->median_seconds ?? 0), 2),
            'p90_seconds' => round((float) ($result->p90_seconds ?? 0), 2),
            'min_seconds' => round((float) ($result->min_seconds ?? 0), 2),
            'max_seconds' => round((float) ($result->max_seconds ?? 0), 2),
        ];
    }

    protected function resolutionTimeStats(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query, 'resolved_at');

        $result = $query
            ->whereNotNull('resolved_at')
            ->whereNotNull('started_at')
            ->selectRaw("
                AVG(EXTRACT(EPOCH FROM (resolved_at - started_at))) as avg_seconds,
                PERCENTILE_CONT(0.5) WITHIN GROUP (
                    ORDER BY EXTRACT(EPOCH FROM (resolved_at - started_at))
                ) as median_seconds,
                PERCENTILE_CONT(0.9) WITHIN GROUP (
                    ORDER BY EXTRACT(EPOCH FROM (resolved_at - started_at))
                ) as p90_seconds
            ")
            ->first();

        $avg = (float) ($result->avg_seconds ?? 0);

        return [
            'average_seconds' => round($avg, 2),
            'average_formatted' => DurationFormatter::format((int) $avg),
            'median_seconds' => round((float) ($result->median_seconds ?? 0), 2),
            'p90_seconds' => round((float) ($result->p90_seconds ?? 0), 2),
        ];
    }


}