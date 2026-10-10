<?php

namespace App\Services\SystemSettings;

use Illuminate\Support\Facades\Cache;

/**
 * Thin cache wrapper around the resolved settings map.
 *
 * The whole resolved map is cached under a single key so invalidation is a
 * single forget(). Cache is only written after a successful DB commit.
 */
class SystemSettingsCache
{
    public function __construct(
        protected ?string $key = null,
        protected int $ttl = 3600,
    ) {
        $this->key = $key ?: (string) config('system_settings.cache.key', 'system_settings:all');
        $this->ttl = $ttl ?: (int) config('system_settings.cache.ttl', 3600);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function get(): ?array
    {
        $value = Cache::get($this->key);

        return is_array($value) ? $value : null;
    }

    public function put(array $values): void
    {
        Cache::put($this->key, $values, now()->addSeconds($this->ttl));
    }

    public function forget(): void
    {
        Cache::forget($this->key);
    }
}
