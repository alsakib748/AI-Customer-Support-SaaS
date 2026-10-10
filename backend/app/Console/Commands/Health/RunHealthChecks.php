<?php

namespace App\Console\Commands\Health;

use App\Health\Services\HealthCheckRunner;
use App\Health\Services\IncidentDetectionService;
use Illuminate\Console\Command;

/**
 * Runs every registered health check and applies incident detection.
 */
class RunHealthChecks extends Command
{
    protected $signature = 'health:check
        {--check=* : Only run the given check key(s).}
        {--source=scheduled : Incident source label for this run.}';

    protected $description = 'Run platform health checks, persist results and evaluate incidents';

    public function handle(HealthCheckRunner $runner, IncidentDetectionService $incidents): int
    {
        $keys = $this->option('check') ?: null;

        $results = $keys === null
            ? $runner->runAll()
            : array_map(fn (string $key) => $runner->runOne($key), $keys);

        $incidents->evaluate($results, (string) $this->option('source'));

        $this->table(
            ['Check', 'Status', 'Duration', 'Summary'],
            array_map(
                fn ($r) => [$r->key, $r->status->value, $r->durationMs.'ms', $r->summary ?? ''],
                $results,
            ),
        );

        return self::SUCCESS;
    }
}