<?php

namespace App\Console\Commands\Notifications;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Prunes read platform notifications past the retention window in bounded
 * batches. Historical incident records live in System Health regardless.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune
        {--days= : Retention window in days (defaults to config)}
        {--chunk=500 : Rows deleted per batch}';

    protected $description = 'Prune read platform notifications older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('notifications.platform.retention_days', 90));
        $chunk = max((int) $this->option('chunk'), 1);

        if ($days <= 0) {
            $this->warn('Retention window is 0; nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $total = 0;

        while (true) {
            $ids = $this->query()
                ->whereNotNull('read_at')
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $total += $this->query()->whereIn('id', $ids)->delete();
        }

        $this->info("Pruned {$total} platform notification(s) older than {$days} day(s).");

        return self::SUCCESS;
    }

    protected function query()
    {
        return (new DatabaseNotification())
            ->setConnection('central')
            ->newQuery();
    }
}
