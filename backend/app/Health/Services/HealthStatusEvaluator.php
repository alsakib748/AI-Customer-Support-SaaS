<?php

namespace App\Health\Services;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;

/**
 * Reduces a set of check outcomes into a single platform overall status.
 *
 * Rules (in priority order):
 *  1. any critical unhealthy      -> "unhealthy"  (hard outage)
 *  2. any critical unknown/stale  -> "unknown"    (cannot prove availability)
 *  3. any degraded / non-critical unhealthy -> "degraded"
 *  4. otherwise                   -> "healthy"
 *
 * Disabled checks never influence the overall status.
 */
class HealthStatusEvaluator
{
    /**
     * @param array<int, array{result: HealthCheckResult, critical: bool, stale: bool}> $entries
     */
    public function evaluate(array $entries): HealthStatus
    {
        $criticalUnhealthy = false;
        $criticalUnknown = false;
        $degraded = false;

        foreach ($entries as $entry) {
            $result = $entry['result'];
            $critical = (bool) $entry['critical'];

            if ($result->status->isDisabled()) {
                continue;
            }

            $status = $entry['stale'] ? HealthStatus::Unknown : $result->status;

            if ($critical) {
                if ($status->isFailure()) {
                    $criticalUnhealthy = true;
                } elseif ($status->isUnknown()) {
                    $criticalUnknown = true;
                } elseif ($status->isDegraded()) {
                    $degraded = true;
                }
                continue;
            }

            if ($status->isFailure() || $status->isDegraded()) {
                $degraded = true;
            }
        }

        if ($criticalUnhealthy) {
            return HealthStatus::Unhealthy;
        }

        if ($criticalUnknown) {
            return HealthStatus::Unknown;
        }

        return $degraded ? HealthStatus::Degraded : HealthStatus::Healthy;
    }
}