<?php

namespace App\Listeners\Notifications;

use App\Events\Health\PlatformIncidentResolved;
use App\Notifications\Services\PlatformNotificationService;

class NotifyPlatformIncidentResolved
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(PlatformIncidentResolved $event): void
    {
        $this->notifications->sendEvent($event);
    }
}
