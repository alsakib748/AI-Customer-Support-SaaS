<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;

/**
 * Reports the freshness of the most recent successful database backup.
 *
 * The backup job (backend/scripts/backup-databases.sh) writes a marker file on
 * success. This check converts that marker into a health signal so the dashboard
 * can surface a stale or missing backup instead of assuming availability.
 *
 * The check is disabled by default (no BACKUP_MARKER_PATH configured) so it does
 * not affect deployments that have not wired the backup job yet.
 */
class BackupFreshnessCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'backup.freshness';
    }

    protected function perform(): HealthCheckResult
    {
        $marker = (string) $this->config('marker_path', '');
        $maxAgeHours = (int) $this->config('max_age_hours', 26);

        if ($marker === '') {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Disabled,
                'No backup marker configured',
                ['marker_path' => null],
            );
        }

        if (! is_file($marker)) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unhealthy,
                'Backup marker not found',
                ['marker_path' => $marker],
            );
        }

        $mtime = filemtime($marker);

        if ($mtime === false) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unknown,
                'Backup marker is unreadable',
                ['marker_path' => $marker],
            );
        }

        $ageHours = max(0, time() - $mtime) / 3600;
        $metadata = [
            'marker_path' => $marker,
            'age_hours' => round($ageHours, 1),
            'max_age_hours' => $maxAgeHours,
        ];

        if ($maxAgeHours > 0 && $ageHours > $maxAgeHours) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unhealthy,
                'Last successful backup exceeds the maximum age',
                $metadata,
            );
        }

        if ($maxAgeHours > 0 && $ageHours > $maxAgeHours * 0.8) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Degraded,
                'Last successful backup is approaching the maximum age',
                $metadata,
            );
        }

        return HealthCheckResult::make(
            $this->key(),
            HealthStatus::Healthy,
            'Last successful backup is recent',
            $metadata,
        );
    }
}
