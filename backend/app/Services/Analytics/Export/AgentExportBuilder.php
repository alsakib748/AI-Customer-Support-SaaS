<?php
// app/Services/Analytics/Export/AgentExportBuilder.php

namespace App\Services\Analytics\Export;

use App\Models\Tenant\Conversation;
use App\Support\Analytics\AnalyticsPeriod;
use App\Support\Analytics\DurationFormatter;
use Illuminate\Support\Facades\DB;

class AgentExportBuilder
{
    public function build(array $filters): array
    {
        $period = AnalyticsPeriod::fromRequest($filters);

        $rows = Conversation::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id')
            ->selectRaw('COUNT(*) as assigned')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'resolved') as resolved")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('open','pending')) as open")
            ->selectRaw("
                AVG(CASE WHEN resolved_at IS NOT NULL AND started_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (resolved_at - started_at))
                    ELSE NULL END) as avg_resolution_seconds
            ")
            ->groupBy('assigned_user_id')
            ->orderByDesc('assigned')
            ->get();

        $agentIds = $rows->pluck('assigned_user_id')->toArray();
        $names = empty($agentIds) ? [] : DB::connection('central')
            ->table('users')->whereIn('id', $agentIds)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn($u) => [
                $u->id => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: 'User #' . $u->id,
            ])->toArray();

        $mapped = $rows->map(fn($r) => [
            $r->assigned_user_id,
            $names[$r->assigned_user_id] ?? 'User #' . $r->assigned_user_id,
            (int) $r->assigned,
            (int) $r->resolved,
            (int) $r->open,
            DurationFormatter::format((int) ($r->avg_resolution_seconds ?? 0)),
        ]);

        $header = [
            'Agent ID',
            'Agent Name',
            'Assigned',
            'Resolved',
            'Open',
            'Avg Resolution',
        ];

        return [$header, $mapped];
    }
}