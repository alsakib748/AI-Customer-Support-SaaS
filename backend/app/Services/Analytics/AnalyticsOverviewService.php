<?php
// app/Services/Analytics/AnalyticsOverviewService.php

namespace App\Services\Analytics;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Ticket;
use App\Models\Tenant\AIUsage;
use App\Support\Analytics\DurationFormatter;
use App\Support\Analytics\MetricChange;

class AnalyticsOverviewService extends BaseAnalyticsService
{
    public function overview(): array
    {
        return $this->remember('overview', function () {
            return [
                'summary' => $this->summary(),
                'conversation_trend' => $this->conversationTrend(),
                'customer_trend' => $this->customerTrend(),
                'status_breakdown' => $this->statusBreakdown(),
                'ai_summary' => $this->aiSummary(),
                'ticket_summary' => $this->ticketSummary(),
            ];
        });
    }

    /**
     * Main KPI summary.
     */
    protected function summary(): array
    {
        $conn = $this->connection();

        // Current period aggregated in one query each
        $conversations = $this->conversationSummary();
        $customers = $this->customerSummary();
        $tickets = $this->ticketSummaryRaw();
        $ai = $this->aiSummaryRaw();

        // Previous period
        $prevConversations = $this->conversationSummary(true);
        $prevCustomers = $this->customerSummary(true);

        // AI resolution rate
        $aiResolutionRate = $ai['handled'] > 0
            ? round(($ai['resolved'] / $ai['handled']) * 100, 1)
            : 0;

        $prevAiResolutionRate = $prevConversations['ai_resolved_count'] ?? 0;

        return [
            'total_conversations' => MetricChange::calculate(
                $conversations['total'],
                $prevConversations['total']
            ),
            'open_conversations' => [
                'value' => $conversations['open'],
            ],
            'resolved_conversations' => MetricChange::calculate(
                $conversations['resolved'],
                $prevConversations['resolved']
            ),
            'ai_resolution_rate' => MetricChange::calculate(
                $aiResolutionRate,
                0
            ),
            'new_customers' => MetricChange::calculate(
                $customers['new'],
                $prevCustomers['new']
            ),
            'open_tickets' => [
                'value' => $tickets['open'],
            ],
            'avg_response_time' => MetricChange::calculateDuration(
                $conversations['avg_response_seconds'],
                0
            ),
            'avg_resolution_time' => MetricChange::calculateDuration(
                $conversations['avg_resolution_seconds'],
                0
            ),
            'formatted' => [
                'avg_response_time' => DurationFormatter::format(
                    (int) $conversations['avg_response_seconds']
                ),
                'avg_resolution_time' => DurationFormatter::format(
                    (int) $conversations['avg_resolution_seconds']
                ),
            ],
        ];
    }

    /**
     * Conversation summary with conditional aggregation.
     */
    protected function conversationSummary(bool $previous = false): array
    {
        $query = Conversation::query();
        $previous
            ? $this->applyPreviousPeriodRange($query)
            : $this->applyPeriodRange($query);

        $result = $query->selectRaw("
        COUNT(*) as total,
        COUNT(*) FILTER (WHERE status = 'open') as open,
        COUNT(*) FILTER (WHERE status = 'pending') as pending,
        COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
        COUNT(*) FILTER (WHERE status = 'closed') as closed,
        COUNT(*) FILTER (WHERE assigned_user_id IS NULL) as unassigned
    ")->first();

        return [
            'total' => (int) ($result->total ?? 0),
            'open' => (int) ($result->open ?? 0),
            'pending' => (int) ($result->pending ?? 0),
            'resolved' => (int) ($result->resolved ?? 0),
            'closed' => (int) ($result->closed ?? 0),
            'escalated' => 0,
            'unassigned' => (int) ($result->unassigned ?? 0),
            'avg_response_seconds' => $this->avgResponseSeconds($previous),
            'avg_resolution_seconds' => $this->avgResolutionSeconds($previous),
        ];
    }

    /**
     * Average first response time in seconds.
     */
    protected function avgResponseSeconds(bool $previous = false): float
    {
        [$from, $to] = $previous
            ? [$this->period->previousFrom, $this->period->previousTo]
            : [$this->period->from, $this->period->to];

        $row = \DB::connection($this->connection())->selectOne("
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
            WHERE m.sender_type IN ('agent','ai')
              AND m.created_at > fc.first_at
            GROUP BY m.conversation_id
        )
        SELECT AVG(EXTRACT(EPOCH FROM (fr.response_at - fc.first_at))) AS avg_seconds
        FROM first_customer fc
        INNER JOIN first_response fr ON fr.conversation_id = fc.conversation_id
    ", [$from, $to]);

        return (float) ($row->avg_seconds ?? 0);
    }

    /**
     * Average resolution time in seconds.
     */
    protected function avgResolutionSeconds(bool $previous = false): float
    {
        $query = Conversation::query();
        $previous ? $this->applyPreviousPeriodRange($query) : $this->applyPeriodRange($query);

        $result = $query
            ->whereNotNull('resolved_at')
            ->whereNotNull('started_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - started_at))) as avg_seconds')
            ->first();

        return (float) ($result->avg_seconds ?? 0);
    }

    /**
     * Customer summary.
     */
    protected function customerSummary(bool $previous = false): array
    {
        // $query = Customer::query();
        // $previous ? $this->applyPreviousPeriodRange($query) : $this->applyPeriodRange($query);

        // $result = $query->selectRaw("
        //     COUNT(*) as total,
        //     COUNT(*) FILTER (WHERE created_at BETWEEN ? AND ?) as new
        // ", [$this->period->from, $this->period->to])->first();

        $query = Customer::query();
        $previous
            ? $this->applyPreviousPeriodRange($query)
            : $this->applyPeriodRange($query);

        $total = (clone $query)->count();
        $new = (clone $query)->whereBetween('created_at', [
            $this->period->from,
            $this->period->to,
        ])->count();

        return [
            'total' => (int) ($result->total ?? 0),
            'new' => (int) ($result->new ?? 0),
        ];
    }

    /**
     * Ticket summary.
     */
    protected function ticketSummary(): array
    {
        $raw = $this->ticketSummaryRaw();

        return [
            'total' => $raw['total'],
            'open' => $raw['open'],
            'in_progress' => $raw['in_progress'],
            'pending' => $raw['pending'],
            'resolved' => $raw['resolved'],
            'closed' => $raw['closed'],
        ];
    }

    protected function ticketSummaryRaw(): array
    {
        $query = Ticket::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'open') as open,
            COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
            COUNT(*) FILTER (WHERE status = 'pending') as pending,
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
            COUNT(*) FILTER (WHERE status = 'closed') as closed
        ")->first();

        return [
            'total' => (int) ($result->total ?? 0),
            'open' => (int) ($result->open ?? 0),
            'in_progress' => (int) ($result->in_progress ?? 0),
            'pending' => (int) ($result->pending ?? 0),
            'resolved' => (int) ($result->resolved ?? 0),
            'closed' => (int) ($result->closed ?? 0),
        ];
    }

    /**
     * AI summary for overview.
     */
    protected function aiSummary(): array
    {
        $raw = $this->aiSummaryRaw();

        return [
            'requests' => $raw['requests'],
            'resolved' => $raw['resolved'],
            'escalated' => $raw['escalated'],
            'resolution_rate' => $raw['handled'] > 0
                ? round(($raw['resolved'] / $raw['handled']) * 100, 1)
                : 0,
            'tokens' => $raw['tokens'],
            'cost' => round($raw['cost'], 4),
        ];
    }

    protected function aiSummaryRaw(): array
    {
        $query = AIUsage::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as requests,
            COALESCE(SUM(total_tokens), 0) as tokens,
            COALESCE(SUM(estimated_cost), 0) as cost
        ")->first();

        // AI handled/resolved from conversations
        $convQuery = Conversation::query();
        $this->applyPeriodRange($convQuery);

        $convResult = $convQuery->selectRaw("
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
            COUNT(*) as handled
        ")->first();

        return [
            'requests' => (int) ($result->requests ?? 0),
            'tokens' => (int) ($result->tokens ?? 0),
            'cost' => (float) ($result->cost ?? 0),
            'resolved' => (int) ($convResult->resolved ?? 0),
            'escalated' => 0,
            'handled' => (int) ($convResult->handled ?? 0),
        ];
    }

    /**
     * Conversation trend over time.
     */
    protected function conversationTrend(): array
    {
        // $format = $this->dateTruncFormat();

        // $rows = Conversation::query()
        //     ->whereBetween('created_at', [$this->period->from, $this->period->to])
        //     ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as period_label")
        //     ->selectRaw('DATE_TRUNC(?, created_at) as period_start', [$this->interval])
        //     ->selectRaw('COUNT(*) as count')
        //     ->groupBy('period_label', 'period_start')
        //     ->orderBy('period_start')
        //     ->get();

        // return [
        //     'labels' => $rows->pluck('period_label')->toArray(),
        //     'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        // ];

        $format = $this->getPostgresDateFormat();
        $interval = $this->interval;

        $rows = Conversation::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$interval])
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return [
            'labels' => $rows->pluck('label')->toArray(),
            'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Customer growth trend.
     */
    protected function customerTrend(): array
    {
        // $format = $this->dateTruncFormat();

        // $rows = Customer::query()
        //     ->whereBetween('created_at', [$this->period->from, $this->period->to])
        //     ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as period_label")
        //     ->selectRaw('DATE_TRUNC(?, created_at) as period_start', [$this->interval])
        //     ->selectRaw('COUNT(*) as count')
        //     ->groupBy('period_label', 'period_start')
        //     ->orderBy('period_start')
        //     ->get();

        // return [
        //     'labels' => $rows->pluck('period_label')->toArray(),
        //     'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        // ];

        $format = $this->getPostgresDateFormat();
        $interval = $this->interval;

        $rows = Customer::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$interval])
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return [
            'labels' => $rows->pluck('label')->toArray(),
            'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Conversation status distribution.
     */
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
                (int) ($result->open ?? 0),
                (int) ($result->pending ?? 0),
                (int) ($result->resolved ?? 0),
                (int) ($result->closed ?? 0),
            ],
        ];
    }
}