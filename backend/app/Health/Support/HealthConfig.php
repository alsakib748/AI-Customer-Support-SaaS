<?php

namespace App\Health\Support;

/**
 * Reads per-check configuration by its literal registry key.
 *
 * Check keys are intentionally namespaced with dots (for example
 * "ai.provider" or "database.postgres"). Using config('health.checks.ai.provider')
 * would make Laravel treat the dots as nested path separators and fail to find
 * the literal key, silently falling back to defaults. This helper looks the key
 * up directly in the 'health.checks' array so those options actually apply.
 */
final class HealthConfig
{
    public static function check(string $key, string $option, mixed $default = null): mixed
    {
        $checks = (array) config('health.checks', []);

        return $checks[$key][$option] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkAll(string $key): array
    {
        $checks = (array) config('health.checks', []);

        return (array) ($checks[$key] ?? []);
    }
}
