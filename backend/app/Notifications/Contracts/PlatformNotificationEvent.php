<?php

namespace App\Notifications\Contracts;

use App\Notifications\Data\PlatformNotificationData;

/**
 * Contract for a domain event that can be turned into a platform notification.
 * Implemented by events whose whole purpose is to surface an operational,
 * billing, tenant or security signal to Super Admins.
 */
interface PlatformNotificationEvent
{
    public function toPlatformNotification(): PlatformNotificationData;
}
