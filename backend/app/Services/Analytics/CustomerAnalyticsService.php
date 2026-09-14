<?php
// app/Services/Analytics/CustomerAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Ticket;

class CustomerAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('customers', function () {
            return [
                'summary' => $this->summary(),
                'growth_trend' => $this->growthTrend(),
                'status_breakdown' => $this->statusBreakdown(),
                'top_customers' => $this->topCustomers(),
            ];
        });
    }

    protected function summary(): array
    {
        $query = Customer::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'active') as active,
            COUNT(*) FILTER (WHERE status = 'inactive') as inactive,
            COUNT(*) FILTER (WHERE status = 'blocked') as blocked,
            COUNT(*) FILTER (WHERE total_conversations > 0) as with_conversations,
            COUNT(*) FILTER (WHERE total_tickets > 0) as with_tickets
        ")->first();

        // Returning customers = those who had activity before the period
        $returning = Conversation::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->whereIn('customer_id', function ($sub) {
                $sub->select('customer_id')
                    ->from('conversations')
                    ->where('created_at', '<', $this->period->from)
                    ->distinct();
            })
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'total' => (int) $result->total,
            'new' => (int) $result->total, // Customers created in period
            'active' => (int) $result->active,
            'inactive' => (int) $result->inactive,
            'blocked' => (int) $result->blocked,
            'with_conversations' => (int) $result->with_conversations,
            'with_tickets' => (int) $result->with_tickets,
            'returning' => (int) $returning,
        ];
    }

    protected function growthTrend(): array
    {
        $format = $this->dateTruncFormat();

        $rows = Customer::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$this->interval])
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return [
            'labels' => $rows->pluck('label')->toArray(),
            'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    protected function statusBreakdown(): array
    {
        $query = Customer::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) FILTER (WHERE status = 'active') as active,
            COUNT(*) FILTER (WHERE status = 'inactive') as inactive,
            COUNT(*) FILTER (WHERE status = 'blocked') as blocked
        ")->first();

        return [
            'labels' => ['Active', 'Inactive', 'Blocked'],
            'values' => [
                (int) $result->active,
                (int) $result->inactive,
                (int) $result->blocked,
            ],
        ];
    }

    protected function topCustomers(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query);

        return $query
            ->with('customer:id,first_name,last_name,email')
            ->selectRaw('customer_id, COUNT(*) as conversation_count')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('conversation_count')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'customer_id' => $row->customer_id,
                'name' => $row->customer?->full_name ?? 'Unknown',
                'email' => $row->customer?->email,
                'conversations' => (int) $row->conversation_count,
            ])
            ->toArray();
    }
}
