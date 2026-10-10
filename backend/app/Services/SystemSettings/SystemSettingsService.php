<?php

namespace App\Services\SystemSettings;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Typed accessor for platform-wide settings.
 *
 * Modules must consume settings through this service (getAiDefaults(),
 * getGeneral(), ...) rather than querying the system_settings table directly.
 * Values are cached as one resolved map and invalidated after every commit.
 */
class SystemSettingsService
{
    public function __construct(
        protected SystemSettingsRepository $repository,
        protected SystemSettingsSchema $schema,
        protected SystemSettingsCache $cache,
        protected AuditLogService $audit,
    ) {
    }

    // =========================================================
    // READ
    // =========================================================

    /**
     * Fully resolved settings map (defaults merged with stored values).
     * Secrets are decrypted — never expose this to the browser directly.
     */
    public function all(): array
    {
        $cached = $this->cache->get();
        if ($cached !== null) {
            return $cached;
        }

        $stored = $this->repository->storedValues();
        $resolved = $this->schema->defaults();

        foreach ($stored as $key => $value) {
            if (! $this->schema->hasKey($key)) {
                continue; // ignore orphaned rows outside the allow-list
            }

            $resolved[$key] = $this->schema->isSensitive($key)
                ? $this->decryptSecret($value)
                : $this->schema->cast($key, $value);
        }

        $this->cache->put($resolved);

        return $resolved;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->schema->hasKey($key)) {
            return $default;
        }

        $values = $this->all();

        return $values[$key] ?? $default;
    }

    /**
     * @param array<int,string> $keys
     */
    public function getMany(array $keys): array
    {
        $values = $this->all();

        return collect($keys)->mapWithKeys(fn ($key) => [$key => $values[$key] ?? null])->all();
    }

    public function group(string $group): array
    {
        $values = $this->all();

        return collect($this->schema->groupKeys($group))
            ->mapWithKeys(fn ($key) => [$key => $values[$key] ?? null])
            ->all();
    }

    /**
     * Only fields flagged public — safe for non-admin consumption.
     */
    public function publicValues(): array
    {
        $values = $this->all();

        return collect($values)
            ->filter(fn ($value, $key) => $this->schema->isPublic($key))
            ->all();
    }

    // =========================================================
    // TYPED ACCESSORS (used by other modules)
    // =========================================================

    public function getGeneral(): array
    {
        return $this->group('general');
    }

    public function getSecurity(): array
    {
        return $this->group('security');
    }

    public function getAiDefaults(): array
    {
        return $this->group('ai');
    }

    public function getEmail(): array
    {
        return $this->group('email');
    }

    public function getIntegrations(): array
    {
        return $this->group('integrations');
    }

    public function getOperations(): array
    {
        return $this->group('operations');
    }

    public function isMaintenanceEnabled(): bool
    {
        return (bool) $this->get('operations.maintenance_enabled', false);
    }

    // =========================================================
    // WRITE
    // =========================================================

    /**
     * Persist an allow-listed subset of one group's settings.
     *
     * @param array<string,mixed> $input
     * @return array redacted group payload
     */
    public function updateGroup(string $group, array $input, ?User $actor = null): array
    {
        $allowed = $this->schema->groupKeys($group);
        $before  = $this->all();

        // Only allow keys that belong to this group.
        $input = array_intersect_key($input, array_flip($allowed));

        $auditOld = [];
        $auditNew = [];

        DB::connection('central')->transaction(function () use ($group, $input, $actor, $before, &$auditOld, &$auditNew) {
            foreach ($input as $key => $raw) {
                $sensitive = $this->schema->isSensitive($key);
                $casted    = $this->schema->cast($key, $raw);

                $oldValue = $before[$key] ?? null;
                $newValue = $sensitive ? $this->normalizeSecret($casted) : $casted;

                if ($oldValue === $casted) {
                    continue; // unchanged
                }

                $this->repository->upsert(
                    $key,
                    $sensitive ? $this->encryptSecret((string) $casted) : $casted,
                    $group,
                    $this->schema->type($key),
                    $this->schema->isPublic($key),
                    $actor?->id,
                );

                $auditOld[$key] = $sensitive ? '[redacted]' : $oldValue;
                $auditNew[$key] = $sensitive ? '[redacted]' : $newValue;
            }
        });

        // Invalidate only after a successful commit.
        $this->cache->forget();

        $this->writeAudit($group, $actor, $auditOld, $auditNew);

        return $this->groupPayload($group);
    }

    /**
     * Reset a group back to its schema defaults.
     */
    public function resetGroup(string $group, ?User $actor = null): array
    {
        $before = $this->all();

        DB::connection('central')->transaction(function () use ($group, $actor, $before) {
            foreach ($this->schema->groupKeys($group) as $key) {
                $this->repository->delete($key);
            }
        });

        $this->cache->forget();

        $this->audit->log(
            'settings.' . $group . '.reset',
            'system_setting',
            null,
            $before,
            null,
            ['actor_id' => $actor?->id, 'group' => $group],
        );

        return $this->groupPayload($group);
    }

    // =========================================================
    // API PAYLOAD
    // =========================================================

    /**
     * Schema metadata for every group (used by the settings hub page).
     */
    public function schemaGroups(): array
    {
        return array_map(
            fn (string $group) => $this->schema->groupMeta($group),
            array_keys($this->schema->groups())
        );
    }

    /**
     * Redacted payload for a single group, safe to return to the browser.
     * Secrets are stripped and replaced with a "configured" flag.
     */
    public function groupPayload(string $group): array
    {
        $resolved = $this->all();
        $values   = [];
        $secrets  = [];

        foreach ($this->schema->groupKeys($group) as $key) {
            if ($this->schema->isSensitive($key)) {
                $value = $resolved[$key] ?? null;
                $secrets[$key] = ! empty($value);
                $values[$key]  = null; // never expose the secret
                continue;
            }

            $values[$key] = $resolved[$key] ?? null;
        }

        $rows = \App\Models\SystemSetting::query()
            ->group($group)
            ->orderByDesc('updated_at')
            ->first();

        return [
            'group'  => $this->schema->groupMeta($group),
            'values' => $values,
            'secrets' => $secrets,
            'meta'   => [
                'updated_at' => optional($rows?->updated_at)->toISOString(),
                'updated_by' => $rows?->updated_by,
            ],
        ];
    }

    // =========================================================
    // CONFIG PROPAGATION
    // =========================================================

    /**
     * Apply the stored email sender identity to the runtime mail configuration.
     * Transport credentials remain in the environment (never stored here).
     */
    public function applyMailConfiguration(): void
    {
        $email = $this->getEmail();

        if (! empty($email['email.sender_email'])) {
            config(['mail.from.address' => $email['email.sender_email']]);
        }

        if (! empty($email['email.sender_name'])) {
            config(['mail.from.name' => $email['email.sender_name']]);
        }

        if (! empty($email['email.reply_to_email'])) {
            config(['mail.reply_to.address' => $email['email.reply_to_email']]);
        }
    }

    // =========================================================
    // INTERNALS
    // =========================================================

    protected function writeAudit(string $group, ?User $actor, array $old, array $new): void
    {
        if (empty($old) && empty($new)) {
            return;
        }

        $this->audit->log(
            'settings.' . $group . '.updated',
            'system_setting',
            null,
            $old,
            $new,
            ['actor_id' => $actor?->id, 'group' => $group],
        );

        // Special-case operational events for easy auditing/filtering.
        if ($group === 'operations' && array_key_exists('operations.maintenance_enabled', $new)) {
            $enabled = (bool) ($new['operations.maintenance_enabled'] ?? false);
            $this->audit->log(
                $enabled ? 'settings.maintenance_enabled' : 'settings.maintenance_disabled',
                'system_setting',
                null,
                null,
                ['enabled' => $enabled, 'message' => $new['operations.maintenance_message'] ?? null],
                ['actor_id' => $actor?->id],
            );
        }

        // Credential rotation is recorded without the credential contents.
        foreach ($new as $key => $value) {
            if ($value === '[redacted]') {
                $this->audit->log(
                    'settings.credential_rotated',
                    'system_setting',
                    null,
                    null,
                    ['key' => $key],
                    ['actor_id' => $actor?->id, 'group' => $group],
                );
            }
        }
    }

    protected function encryptSecret(string $plain): array
    {
        return ['__encrypted' => Crypt::encryptString($plain)];
    }

    protected function decryptSecret(mixed $value): ?string
    {
        if (is_array($value) && isset($value['__encrypted'])) {
            try {
                return Crypt::decryptString($value['__encrypted']);
            } catch (\Throwable $e) {
                return null; // key rotated / corrupted — treat as unset
            }
        }

        return is_null($value) ? null : (string) $value;
    }

    protected function normalizeSecret(mixed $value): ?string
    {
        return is_null($value) || $value === '' ? null : (string) $value;
    }
}
