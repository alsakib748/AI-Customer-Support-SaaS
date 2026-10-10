<?php

namespace App\Services\SystemSettings;

use App\Models\SystemSetting;

/**
 * Low-level persistence for system settings. Always operates on the central
 * connection. Application code should depend on SystemSettingsService instead.
 */
class SystemSettingsRepository
{
    /**
     * Raw stored rows keyed by setting key.
     *
     * @return array<string, SystemSetting>
     */
    public function rows(): array
    {
        return SystemSetting::query()
            ->get()
            ->keyBy('key')
            ->all();
    }

    /**
     * Stored values keyed by setting key (still encrypted for secrets).
     *
     * @return array<string, mixed>
     */
    public function storedValues(): array
    {
        return SystemSetting::query()
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->value])
            ->all();
    }

    public function upsert(
        string $key,
        mixed $value,
        string $group,
        string $type,
        bool $isPublic,
        ?int $updatedBy = null,
    ): SystemSetting {
        return SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value'      => $value,
                'group'      => $group,
                'type'       => $type,
                'is_public'  => $isPublic,
                'updated_by' => $updatedBy,
            ]
        );
    }

    public function delete(string $key): void
    {
        SystemSetting::where('key', $key)->delete();
    }
}
