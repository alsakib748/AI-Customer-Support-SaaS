<?php

namespace App\Notifications;

use App\Notifications\Data\PlatformNotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The single Laravel notification class used for every platform notification.
 * It persists a normalized payload to the database channel; other channels
 * (email, broadcast) can be added later behind preferences.
 */
class PlatformNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly PlatformNotificationData $data,
    ) {
    }

    /**
     * @return string[]
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->data->toArray();
    }
}
