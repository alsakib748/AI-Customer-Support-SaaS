<?php
// app/Services/Analytics/AIAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\AIUsage;
use App\Models\Tenant\AILog;
use App\Models\Tenant\Conversation;

class AIAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('ai', function () {
            return [
                'summary' => $this->summary(),
                'usage_trend' => $this->usageTrend(),
                'provider_breakdown' => $this->providerBreakdown(),
                'model_breakdown' => $this->modelBreakdown(),
                'escalation' => $this->escalation(),
                'health' => $this->health(),
            ];
        });
    }

    protected function summary(): array
    {
        $usage = AIUsage::query();
        $this->applyPeriodRange($usage);
        $usageResult = $usage->selectRaw("
            COUNT(*) as requests,
            COALESCE(SUM(input_tokens), 0) as input_tokens,
            COALESCE(SUM(output_tokens), 0) as output_tokens,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(SUM(estimated_cost), 0) as total_cost
        ")->first();

        $conv = Conversation::query();
        $this->applyPeriodRange($conv);
        $convResult = $conv->selectRaw("
            COUNT(*) as handled,
            COUNT(*) FILTER (WHERE status = 'resolved') as resolved
        ")->first();

        $handled = (int) $convResult->handled;
        $resolved = (int) $convResult->resolved;
        $escalated = 0;
        $requests = (int) $usageResult->requests;

        return [
            'requests' => $requests,
            'input_tokens' => (int) $usageResult->input_tokens,
            'output_tokens' => (int) $usageResult->output_tokens,
            'total_tokens' => (int) $usageResult->total_tokens,
            'total_cost' => round((float) $usageResult->total_cost, 4),
            'avg_cost_per_request' => $requests > 0
                ? round((float) $usageResult->total_cost / $requests, 6)
                : 0,
            'handled' => $handled,
            'resolved' => $resolved,
            'escalated' => $escalated,
            'resolution_rate' => $handled > 0 ? round(($resolved / $handled) * 100, 1) : 0,
            'escalation_rate' => $handled > 0 ? round(($escalated / $handled) * 100, 1) : 0,
        ];
    }

    protected function usageTrend(): array
    {
        $format = $this->dateTruncFormat();

        $rows = AIUsage::query()
            ->whereBetween('created_at', [$this->period->from, $this->period->to])
            ->selectRaw("TO_CHAR(DATE_TRUNC('{$this->interval}', created_at), '{$format}') as label")
            ->selectRaw('DATE_TRUNC(?, created_at) as sort_key', [$this->interval])
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens')
            ->groupBy('label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return [
            'labels' => $rows->pluck('label')->toArray(),
            'datasets' => [
                [
                    'label' => 'Requests',
                    'values' => $rows->pluck('requests')->map(fn($v) => (int) $v)->toArray(),
                ],
                [
                    'label' => 'Tokens',
                    'values' => $rows->pluck('tokens')->map(fn($v) => (int) $v)->toArray(),
                ],
            ],
        ];
    }

    protected function providerBreakdown(): array
    {
        $query = AIUsage::query();
        $this->applyPeriodRange($query);

        $rows = $query->selectRaw('provider, COUNT(*) as count')
            ->groupBy('provider')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $rows->pluck('provider')->map(fn($p) => ucfirst($p))->toArray(),
            'values' => $rows->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    protected function modelBreakdown(): array
    {
        $query = AIUsage::query();
        $this->applyPeriodRange($query);

        return $query->selectRaw("
            model,
            COUNT(*) as requests,
            COALESCE(SUM(total_tokens), 0) as tokens,
            COALESCE(SUM(estimated_cost), 0) as cost
        ")
            ->groupBy('model')
            ->orderByDesc('requests')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'model' => $r->model,
                'requests' => (int) $r->requests,
                'tokens' => (int) $r->tokens,
                'cost' => round((float) $r->cost, 4),
            ])
            ->toArray();
    }

    protected function escalation(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query);

        $total = (int) (clone $query)->count();
        $escalated = 0;

        return [
            'total_escalations' => $escalated,
            'escalation_rate' => $total > 0 ? round(($escalated / $total) * 100, 1) : 0,
        ];
    }

    protected function health(): array
    {
        $query = AILog::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'success') as success,
            COUNT(*) FILTER (WHERE status = 'failed') as failed,
            AVG(duration_ms) FILTER (WHERE status = 'success') as avg_duration
        ")->first();

        $total = (int) ($result->total ?? 0);
        $success = (int) ($result->success ?? 0);

        return [
            'total_requests' => $total,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0,
            'failure_rate' => $total > 0 ? round((((int) $result->failed) / $total) * 100, 1) : 0,
            'avg_response_time_ms' => round((float) ($result->avg_duration ?? 0), 2),
        ];
    }
}