<?php

namespace App\Console\Commands\Health;

use App\Jobs\Health\HealthHeartbeatJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Writes the scheduler heartbeat and dispatches a worker heartbeat probe.
 */
class Heartbeat extends Command
{
    protected $signature = 'health:heartbeat';

    protected $description = 'Record scheduler liveness and dispatch a queue worker heartbeat';

    public function handle(): int
    {
        Cache::put(
            config('health.heartbeat.scheduler_key'),
            Carbon::now()->getTimestamp(),
            (int) config('health.heartbeat.worker_ttl', 900),
        );

        HealthHeartbeatJob::dispatch();

        return self::SUCCESS;
    }
}