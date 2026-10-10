<?php

namespace App\Health\Services;

use App\Health\DTOs\HealthCheckResult;
use App\Models\HealthCheckResult as HealthCheckResultModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Persists raw health-check outcomes and queries the stored snapshots. Reads
 * never execute checks - the dashboard always consumes stored data.
 */
class HealthCheckStorage
{
    public const SNAPSHOT_KEY = 'health:snapshot:v1';

    protected int $snapshotTtl = 15;

    public function persist(HealthCheckResult $result): void
    {
        HealthCheckResultModel::create([
            'check_key'   => $result->key,
            'status'      => $result->status->value,
            'summary'     => $result->summary,
            'metadata'    => $result->metadata,
            'duration_ms' => $result->durationMs,
            'checked_at'  => Carbon::now(),
        ]);

        Cache::forget(self::SNAPSHOT_KEY);
    }

    public function latestFor(string $key): ?HealthCheckResultModel
    {
        return HealthCheckResultModel::query()
            ->where('check_key', $key)
            ->orderByDesc('checked_at')
            ->first();
    }

    /**
     * Latest stored row per check key, keyed by check key (models retain
     * checked_at so the snapshot service can grade staleness).
     *
     * @return array<string, HealthCheckResultModel>
     */
    public function allLatest(): array
    {
        $latestIds = DB::connection('central')
            ->table((new HealthCheckResultModel)->getTable())
            ->selectRaw('MAX(id) as id')
            ->groupBy('check_key')
            ->pluck('id');

        return HealthCheckResultModel::query()
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy(fn (HealthCheckResultModel $row) => $row->check_key)
            ->all();
    }

    /**
     * @return HealthCheckResultModel[]
     */
    public function history(string $key, int $limit = 60): array
    {
        return HealthCheckResultModel::query()
            ->where('check_key', $key)
            ->orderByDesc('checked_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}