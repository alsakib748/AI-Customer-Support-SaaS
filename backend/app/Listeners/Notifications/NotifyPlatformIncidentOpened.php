<?php

namespace App\Listeners\Notifications;

use App\Events\Health\PlatformIncidentOpened;
use App\Notifications\Services\PlatformNotificationService;

class NotifyPlatformIncidentOpened
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(PlatformIncidentOpened $event): void
    {
        $this->notifications->sendEvent($event);
    }
}
