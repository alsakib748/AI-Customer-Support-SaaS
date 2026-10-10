<?php

namespace App\Health\Contracts;

use App\Health\DTOs\HealthCheckResult;

/**
 * Contract every platform health check must honour.
 *
 * Checks run synchronously from worker-less contexts (HTTP + scheduler). They
 * must never mutate application state, must guard their own timeouts and must
 * throw only when something genuinely breaks - the runner normalises any
 * exception into an Unhealthy result.
 */
interface HealthCheck
{
    /**
     * Stable, url-safe identifier, e.g. "database.postgres".
     */
    public function key(): string;

    /**
     * Human readable label shown on the dashboard.
     */
    public function label(): string;

    /**
     * Whether a failure of this check warrants a critical platform-wide status.
     */
    public function critical(): bool;

    /**
     * Execute the check and return a result. Must not throw.
     */
    public function run(): HealthCheckResult;
}