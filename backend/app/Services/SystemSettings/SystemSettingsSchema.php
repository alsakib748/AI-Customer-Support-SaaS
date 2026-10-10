<?php

namespace App\Services\SystemSettings;

/**
 * Reads config/system_settings.php and exposes the typed, allow-listed schema.
 *
 * This is the only place that knows how settings keys map to types, defaults
 * and validation rules. Everything else works through this contract.
 */
class SystemSettingsSchema
{
    public function __construct(
        protected array $config = [],
    ) {
        $this->config = $config ?: (array) config('system_settings', []);
    }

    public function groups(): array
    {
        return (array) ($this->config['groups'] ?? []);
    }

    public function hasGroup(string $group): bool
    {
        return array_key_exists($group, $this->groups());
    }

    public function group(string $group): ?array
    {
        return $this->groups()[$group] ?? null;
    }

    public function groupKeys(string $group): array
    {
        return array_keys((array) ($this->group($group)['fields'] ?? []));
    }

    /**
     * Flattened key => field definition map.
     */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->groups() as $group) {
            foreach ((array) ($group['fields'] ?? []) as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    public function field(string $key): ?array
    {
        return $this->fields()[$key] ?? null;
    }

    public function keys(): array
    {
        return array_keys($this->fields());
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->fields());
    }

    public function type(string $key): string
    {
        return $this->field($key)['type'] ?? 'string';
    }

    public function isPublic(string $key): bool
    {
        return (bool) ($this->field($key)['public'] ?? false);
    }

    public function isSensitive(string $key): bool
    {
        return (bool) ($this->field($key)['sensitive'] ?? false);
    }

    public function defaultFor(string $key): mixed
    {
        return $this->field($key)['default'] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->fields() as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $defaults;
    }

    /**
     * Validation rules for a whole group (used by FormRequests).
     */
    public function rulesForGroup(string $group): array
    {
        $rules = [];
        foreach ($this->groupKeys($group) as $key) {
            // Every field is optional in an update; fields that declare
            // "required" still reject empty/invalid values when present.
            $fieldRules = (array) ($this->field($key)['rules'] ?? ['nullable']);
            array_unshift($fieldRules, 'sometimes');
            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Cast a raw request value to the field's declared type.
     */
    public function cast(string $key, mixed $value): mixed
    {
        return match ($this->type($key)) {
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int'   => is_null($value) ? null : (int) $value,
            'float' => is_null($value) ? null : (float) $value,
            'array' => $this->castArray($value),
            'text'  => is_null($value) ? null : (string) $value,
            default => is_null($value) ? null : (string) $value,
        };
    }

    protected function castArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    /**
     * Public metadata for a single field, safe to send to the browser.
     * Secret values are never included.
     */
    public function fieldMeta(string $key): array
    {
        $field = $this->field($key) ?? [];

        $meta = [
            'key'       => $key,
            'label'     => $field['label'] ?? $key,
            'type'      => $this->type($key),
            'public'    => $this->isPublic($key),
            'sensitive' => $this->isSensitive($key),
            'help'      => $field['help'] ?? null,
            'options'   => $field['options'] ?? [],
            'min'       => $field['min'] ?? null,
            'max'       => $field['max'] ?? null,
        ];

        // AI provider select options are derived from configured providers.
        if (in_array($key, ['ai.default_provider', 'ai.fallback_provider'], true)) {
            $meta['options'] = $this->providerOptions();
        }

        return $meta;
    }

    public function groupMeta(string $group): array
    {
        $meta = $this->group($group) ?? [];

        return [
            'key'         => $group,
            'label'       => $meta['label'] ?? ucfirst($group),
            'icon'        => $meta['icon'] ?? 'pi pi-cog',
            'description' => $meta['description'] ?? '',
            'fields'      => array_map(
                fn (string $key) => $this->fieldMeta($key),
                $this->groupKeys($group)
            ),
        ];
    }

    /**
     * @return array<int,array{label:string,value:string}>
     */
    public function providerOptions(): array
    {
        return collect(array_keys((array) config('ai.providers', [])))
            ->map(fn (string $provider) => [
                'label' => ucfirst(str_replace(['-', '_'], ' ', $provider)),
                'value' => $provider,
            ])
            ->values()
            ->all();
    }
}
