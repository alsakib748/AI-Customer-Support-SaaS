<?php

namespace App\Health\Services;

use App\Health\Contracts\HealthCheck;
use App\Health\Support\HealthConfig;

/**
 * Resolves the allow-listed checks from config. Only enabled checks are made
 * available, so the registry itself enforces the "define in configuration"
 * policy.
 */
class HealthCheckRegistry
{
    public function __construct(protected array $classes = [])
    {
        $this->classes = $classes ?: (array) config('health.check_classes', []);
    }

    /**
     * @return HealthCheck[]
     */
    public function all(): array
    {
        return collect($this->classes)
            ->map(fn (string $class) => app($class))
            ->filter(fn (mixed $check) => $check instanceof HealthCheck)
            ->filter(fn (HealthCheck $check) => $this->isEnabled($check->key()))
            ->values()
            ->all();
    }

    public function get(string $key): ?HealthCheck
    {
        foreach ($this->all() as $check) {
            if ($check->key() === $key) {
                return $check;
            }
        }

        return null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return collect($this->all())->map(fn (HealthCheck $check) => $check->key())->values()->all();
    }

    protected function isEnabled(string $key): bool
    {
        return (bool) HealthConfig::check($key, 'enabled', true);
    }
}