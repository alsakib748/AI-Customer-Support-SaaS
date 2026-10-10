<?php

namespace App\Health\Services;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Models\HealthCheckResult as HealthCheckResultModel;
use App\Models\PlatformIncident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the read-only dashboard snapshot purely from stored results; it never
 * executes a live probe. Graded "stale" results are shown as unknown so the
 * panel does not claim availability it cannot prove.
 */
class HealthSnapshotService
{
    public function __construct(
        protected HealthCheckStorage $storage,
        protected HealthStatusEvaluator $evaluator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = Cache::get(HealthCheckStorage::SNAPSHOT_KEY);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $snapshot = $this->build();
        Cache::put(HealthCheckStorage::SNAPSHOT_KEY, $snapshot, 15);

        return $snapshot;
    }

    public static function forgetCache(): void
    {
        Cache::forget(HealthCheckStorage::SNAPSHOT_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    protected function build(): array
    {
        $now = Carbon::now();
        $defaultStaleAfter = (int) config('health.stale_after_seconds', 600);

        /** @var array<string, HealthCheckResultModel> $latest */
        $latest = $this->storage->allLatest();

        $entries = [];
        $checks = [];
        $counts = [
            'healthy'   => 0,
            'degraded'  => 0,
            'unhealthy' => 0,
            'unknown'   => 0,
            'disabled'  => 0,
        ];

        foreach ((array) config('health.checks', []) as $key => $checkConfig) {
            $critical = (bool) ($checkConfig['critical'] ?? false);
            $enabled = (bool) ($checkConfig['enabled'] ?? true);
            $label = (string) ($checkConfig['label'] ?? $key);
            $staleAfter = (int) ($checkConfig['stale_after_seconds'] ?? $defaultStaleAfter);

            $row = $latest[$key] ?? null;
            $dto = $row?->toDto() ?? $this->noDataResult($key);
            $stale = $dto->checkedAtIsStale($now, $staleAfter);

            $status = $this->resolveStatus($dto->status, $enabled, $stale);
            $counts[$status->value]++;

            $checks[] = [
                'key'                 => $key,
                'label'               => $label,
                'critical'            => $critical,
                'status'              => $status->value,
                'summary'             => $dto->summary,
                'duration_ms'         => $dto->durationMs,
                'metadata'            => $dto->metadata,
                'checked_at'          => $dto->checkedAtIso(),
                'stale'               => $stale,
                'stale_after_seconds' => $staleAfter,
            ];

            if ($enabled) {
                $entries[] = [
                    'result'   => $dto,
                    'critical' => $critical,
                    'stale'    => $stale,
                ];
            }
        }

        return [
            'overall'             => $this->evaluator->evaluate($entries)->value,
            'generated_at'        => $now->toIso8601String(),
            'counts'              => $counts,
            'active_incidents'    => (int) PlatformIncident::query()->active()->count(),
            'stale_after_seconds' => $defaultStaleAfter,
            'checks'              => $checks,
        ];
    }

    protected function resolveStatus(HealthStatus $status, bool $enabled, bool $stale): HealthStatus
    {
        // Intentionally inactive integrations stay "disabled" regardless of how
        // old their last result is - staleness must not turn them into unknowns.
        if (! $enabled || $status->isDisabled()) {
            return HealthStatus::Disabled;
        }

        if ($stale) {
            return HealthStatus::Unknown;
        }

        return $status;
    }

    protected function noDataResult(string $key): HealthCheckResult
    {
        return HealthCheckResult::make($key, HealthStatus::Unknown, 'No results recorded yet');
    }
}