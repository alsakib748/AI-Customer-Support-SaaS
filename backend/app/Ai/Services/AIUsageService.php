<?php
// app/Ai/Services/AIUsageService.php

namespace App\Ai\Services;

use App\Models\Tenant\AIUsage;
use App\Models\Tenant\AILog;
use Carbon\Carbon;

class AIUsageService
{
    /**
     * Get usage statistics
     */
    public function getStatistics(string $tenantId, ?string $period = 'month'): array
    {
        $query = AIUsage::query();

        if ($period === 'week') {
            $startDate = Carbon::now()->startOfWeek();
        } elseif ($period === 'month') {
            $startDate = Carbon::now()->startOfMonth();
        } elseif ($period === 'year') {
            $startDate = Carbon::now()->startOfYear();
        } else {
            $startDate = Carbon::now()->subDays(30);
        }

        $query->where('created_at', '>=', $startDate);

        $usage = [
            'total_requests' => $query->count(),
            'total_tokens' => $query->sum('total_tokens'),
            'total_cost' => $query->sum('estimated_cost'),
            'by_provider' => (clone $query)
                ->selectRaw('provider, count(*) as count, sum(total_tokens) as tokens, sum(estimated_cost) as cost')
                ->groupBy('provider')
                ->get()
                ->toArray(),
            'by_model' => (clone $query)
                ->selectRaw('model, count(*) as count, sum(total_tokens) as tokens')
                ->groupBy('model')
                ->get()
                ->toArray(),
            'daily_usage' => (clone $query)
                ->selectRaw('DATE(created_at) as date, count(*) as count, sum(total_tokens) as tokens')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get()
                ->toArray(),
        ];

        return $usage;
    }

    /**
     * Get AI health metrics
     */
    public function getHealthMetrics(string $tenantId): array
    {
        $last24Hours = Carbon::now()->subHours(24);

        $logs = AILog::where('created_at', '>=', $last24Hours);

        $total = $logs->count();
        $success = (clone $logs)->where('status', 'success')->count();
        $failed = (clone $logs)->where('status', 'failed')->count();

        $avgDuration = (clone $logs)->where('status', 'success')->avg('duration_ms');

        return [
            'total_requests' => $total,
            'success_rate' => $total > 0 ? ($success / $total) * 100 : 0,
            'failure_rate' => $total > 0 ? ($failed / $total) * 100 : 0,
            'avg_response_time' => round($avgDuration ?? 0, 2),
            'status' => $failed > ($total * 0.1) ? 'degraded' : 'healthy',
        ];
    }

    /**
     * Get AI analytics for dashboard
     */
    public function getAnalytics(string $tenantId): array
    {
        $total = AIUsage::count();
        $cost = AIUsage::sum('estimated_cost');

        $byDay = AIUsage::selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(7)
            ->get()
            ->toArray();

        return [
            'total_requests' => $total,
            'total_cost' => $cost,
            'avg_cost_per_request' => $total > 0 ? $cost / $total : 0,
            'trend' => $byDay,
        ];
    }
}