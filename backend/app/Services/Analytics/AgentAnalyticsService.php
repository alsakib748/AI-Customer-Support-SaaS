<?php
// app/Services/Analytics/AgentAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Ticket;
use App\Support\Analytics\DurationFormatter;
use Illuminate\Support\Facades\DB;

class AgentAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('agents', function () {
            return [
                'summary' => $this->summary(),
                'performance_table' => $this->performanceTable(),
                'workload' => $this->workload(),
            ];
        });
    }

    protected function summary(): array
    {
        $query = Conversation::query();
        $this->applyPeriodRange($query);

        $result = $query->selectRaw("
            COUNT(DISTINCT assigned_user_id) as active_agents,
            COUNT(*) FILTER (WHERE assigned_user_id IS NULL) as unassigned
        ")->first();

        return [
            'active_agents' => (int) $result->active_agents,
            'unassigned_conversations' => (int) $result->unassigned,
        ];
    }

    protected function performanceTable(): array
    {
        // Aggregate per assigned user
        $rows = Conversation::query()
            ->whereBetween('conversations.created_at', [$this->period->from, $this->period->to])
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id')
            ->selectRaw('COUNT(*) as assigned')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'resolved') as resolved")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'closed') as closed")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('open','pending')) as open")
            ->selectRaw("
                AVG(
                    CASE WHEN resolved_at IS NOT NULL AND started_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (resolved_at - started_at))
                    ELSE NULL END
                ) as avg_resolution_seconds
            ")
            ->groupBy('assigned_user_id')
            ->orderByDesc('assigned')
            ->get();

        // Get agent names from central DB in one query
        $agentIds = $rows->pluck('assigned_user_id')->toArray();
        $agents = $this->getAgentNames($agentIds);

        return $rows->map(function ($row) use ($agents) {
            $resolutionSeconds = (float) ($row->avg_resolution_seconds ?? 0);
            return [
                'agent_id' => $row->assigned_user_id,
                'agent_name' => $agents[$row->assigned_user_id] ?? 'User #' . $row->assigned_user_id,
                'assigned' => (int) $row->assigned,
                'resolved' => (int) $row->resolved,
                'closed' => (int) $row->closed,
                'open' => (int) $row->open,
                'resolution_rate' => $row->assigned > 0
                    ? round(($row->resolved / $row->assigned) * 100, 1)
                    : 0,
                'avg_resolution_seconds' => round($resolutionSeconds, 2),
                'avg_resolution_formatted' => DurationFormatter::format((int) $resolutionSeconds),
            ];
        })->toArray();
    }

    protected function workload(): array
    {
        // Current open conversation counts per agent
        $rows = Conversation::query()
            ->whereIn('status', ['open', 'pending'])
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) as open_count')
            ->groupBy('assigned_user_id')
            ->orderByDesc('open_count')
            ->limit(15)
            ->get();

        $agentIds = $rows->pluck('assigned_user_id')->toArray();
        $agents = $this->getAgentNames($agentIds);

        return [
            'labels' => $rows->map(fn($r) => $agents[$r->assigned_user_id] ?? 'User #' . $r->assigned_user_id)->toArray(),
            'values' => $rows->pluck('open_count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Fetch agent names from central DB in one query.
     */
    protected function getAgentNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::connection('central')
            ->table('users')
            ->whereIn('id', $ids)
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->mapWithKeys(function ($user) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                return [$user->id => $name ?: 'User #' . $user->id];
            })
            ->toArray();
    }
}
