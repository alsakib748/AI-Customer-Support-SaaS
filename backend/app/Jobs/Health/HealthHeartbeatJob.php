<?php

namespace App\Jobs\Health;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Consumed by a real queue worker. The timestamp it writes is the proof the
 * queue is actually draining, which the QueueHealthCheck reads.
 */
class HealthHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 5;

    public function handle(): void
    {
        Cache::put(
            config('health.heartbeat.worker_key'),
            Carbon::now()->getTimestamp(),
            (int) config('health.heartbeat.worker_ttl', 900),
        );
    }
}