<?php

namespace App\Notifications\Services;

use App\Models\User;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;

/**
 * Decides whether a given Super Admin wants a given optional notification.
 *
 * Preferences are read from the existing User.preferences JSON column so no
 * additional table is required for the first release. Security and critical
 * operational alerts are mandatory and cannot be muted by a personal
 * preference.
 */
class NotificationPreferenceService
{
    public function shouldDeliver(User $user, PlatformNotificationData $data): bool
    {
        if ($this->isMandatory($data)) {
            return true;
        }

        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $channels    = $preferences['notifications'] ?? [];

        if (array_key_exists($data->category->value, $channels)) {
            return (bool) $channels[$data->category->value];
        }

        return (bool) config(
            "notifications.platform.categories.{$data->category->value}",
            true,
        );
    }

    protected function isMandatory(PlatformNotificationData $data): bool
    {
        return $data->category === NotificationCategory::Security
            || $data->severity->isAtLeast(NotificationSeverity::Critical);
    }
}
