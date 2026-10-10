<?php

namespace App\Console\Commands\Health;

use App\Models\HealthMetricSample;
use App\Models\HealthCheckResult;
use App\Models\PlatformIncidentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Removes expired monitoring rows so the tables stay bounded.
 */
class PruneHealthData extends Command
{
    protected $signature = 'health:prune';

    protected $description = 'Prune expired health check results, metric samples and incident events';

    public function handle(): int
    {
        $tables = [
            [HealthCheckResult::class, 'checked_at', (int) config('health.retention.check_results_days', 30)],
            [HealthMetricSample::class, 'recorded_at', (int) config('health.retention.metric_samples_days', 30)],
            [PlatformIncidentEvent::class, 'created_at', (int) config('health.retention.incident_events_days', 365)],
        ];

        $deleted = [];

        foreach ($tables as [$model, $column, $days]) {
            $instance = new $model;
            $table = $instance->getTable();
            $connection = $instance->getConnection();
            $cutoff = Carbon::now()->subDays($days);

            $deleted[$table] = DB::connection($connection->getName())
                ->table($table)
                ->where($column, '<', $cutoff)
                ->delete();
        }

        $this->table(['Table', 'Deleted'], array_map(fn ($count, $table) => [$table, $count], $deleted, array_keys($deleted)));

        return self::SUCCESS;
    }
}