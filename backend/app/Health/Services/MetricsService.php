<?php

namespace App\Health\Services;

use App\Health\DTOs\HealthCheckResult;
use App\Models\HealthMetricSample;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stores lightweight numeric samples derived from health checks so dashboards
 * can render latency / uptime series without hammering live probes.
 */
class MetricsService
{
    public function recordCheck(HealthCheckResult $result): void
    {
        if (! config('health.metrics.enabled', true)) {
            return;
        }

        // Intentionally inactive integrations are not uptime signals; sampling
        // them as "down" would distort historical availability, so skip them.
        if ($result->status->isDisabled()) {
            return;
        }

        $recordedAt = $result->checkedAt ?? Carbon::now();
        $now = Carbon::now();

        DB::connection('central')->table((new HealthMetricSample)->getTable())->insert([
            [
                'metric_key'  => 'check.duration_ms',
                'source'      => $result->key,
                'value'       => $result->durationMs,
                'unit'        => 'ms',
                'recorded_at' => $recordedAt,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'metric_key'  => 'check.status',
                'source'      => $result->key,
                'value'       => $result->isHealthy() ? 1 : 0,
                'unit'        => null,
                'recorded_at' => $recordedAt,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }

    /**
     * @return array{metric_key: string, source: ?string, count: int, min: float, max: float, avg: float, samples: array}
     */
    public function query(string $metricKey, ?string $source = null, ?Carbon $from = null, ?Carbon $to = null, int $limit = 500): array
    {
        $query = HealthMetricSample::query()
            ->where('metric_key', $metricKey)
            ->orderByDesc('recorded_at')
            ->limit($limit);

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($from !== null) {
            $query->where('recorded_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('recorded_at', '<=', $to);
        }

        $rows = $query->get();
        $values = $rows->pluck('value')->map(fn ($v) => (float) $v);

        $metrics = $rows->map(fn (HealthMetricSample $s) => [
            'value'       => (float) $s->value,
            'unit'        => $s->unit,
            'recorded_at' => $s->recorded_at->toIso8601String(),
        ])->reverse()->values()->all();

        return [
            'metric_key' => $metricKey,
            'source'     => $source,
            'count'      => $values->count(),
            'min'        => $values->min(),
            'max'        => $values->max(),
            'avg'        => $values->avg(),
            'samples'    => $metrics,
        ];
    }

    /**
     * @return string[]
     */
    public function metricKeys(): array
    {
        return HealthMetricSample::query()->distinct()->pluck('metric_key')->map(fn ($k) => (string) $k)->values()->all();
    }

    /**
     * Historical availability derived from `check.status` samples, kept separate
     * from the instantaneous status snapshot. A sample counts as available when
     * its stored value >= 1 (i.e. the check reported healthy at that point).
     *
     * @return array{from: ?string, to: ?string, available: int, total: int, pct: ?float, by_source: array<string, array{available: int, total: int, pct: ?float}>}
     */
    public function availability(?string $source = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = HealthMetricSample::query()->where('metric_key', 'check.status');

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($from !== null) {
            $query->where('recorded_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('recorded_at', '<=', $to);
        }

        $rows = $query
            ->selectRaw('source, count(*) as total, sum(case when value >= 1 then 1 else 0 end) as available')
            ->groupBy('source')
            ->get();

        $bySource = [];
        $availableAll = 0;
        $totalAll = 0;

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $available = (int) $row->available;

            $availableAll += $available;
            $totalAll += $total;

            $bySource[(string) $row->source] = [
                'available' => $available,
                'total'     => $total,
                'pct'       => $total > 0 ? round($available / $total * 100, 1) : null,
            ];
        }

        return [
            'from'      => $from?->toIso8601String(),
            'to'        => $to?->toIso8601String(),
            'available' => $availableAll,
            'total'     => $totalAll,
            'pct'       => $totalAll > 0 ? round($availableAll / $totalAll * 100, 1) : null,
            'by_source' => $bySource,
        ];
    }
}