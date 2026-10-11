<?php

namespace App\Notifications\Services;

use App\Models\User;
use App\Notifications\Contracts\PlatformNotificationEvent;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Central entry point for sending platform notifications. Producers (domain
 * event listeners, services) call this instead of touching Laravel's
 * notification system directly.
 *
 * Reliability rules implemented here:
 *  - deduplicate repeated events via a stable fingerprint + TTL;
 *  - filter recipients through their preferences;
 *  - dispatch only after the surrounding database transaction commits;
 *  - never let a notification failure bubble up and roll back a business op.
 */
class PlatformNotificationService
{
    public function __construct(
        protected PlatformNotificationRecipientResolver $recipients,
        protected NotificationPreferenceService $preferences,
    ) {
    }

    /**
     * Send a domain event that knows how to describe itself.
     *
     * @param  iterable<User>|null  $recipients
     */
    public function sendEvent(PlatformNotificationEvent $event, ?iterable $recipients = null): int
    {
        return $this->send($event->toPlatformNotification(), $recipients);
    }

    /**
     * @param  iterable<User>|null  $recipients
     * @return int  number of recipients notified
     */
    public function send(PlatformNotificationData $data, ?iterable $recipients = null): int
    {
        if (! config('notifications.platform.enabled', true)) {
            return 0;
        }

        if (! config("notifications.platform.categories.{$data->category->value}", true)) {
            return 0;
        }

        if (! $this->claim($data)) {
            return 0; // duplicate within the dedupe window
        }

        $recipients = $recipients !== null
            ? Collection::make($recipients)
            : $this->recipients->superAdmins();

        $recipients = $recipients
            ->filter(fn (User $user) => $this->preferences->shouldDeliver($user, $data))
            ->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $this->dispatchAfterCommit($recipients, $data);

        return $recipients->count();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    protected function dispatchAfterCommit(Collection $recipients, PlatformNotificationData $data): void
    {
        DB::connection('central')->afterCommit(function () use ($recipients, $data) {
            try {
                Notification::send($recipients, new PlatformNotification($data));
            } catch (\Throwable $e) {
                Log::error('Failed to deliver platform notification.', [
                    'event_key' => $data->eventKey,
                    'category'  => $data->category->value,
                    'severity'  => $data->severity->value,
                    'error'     => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Atomically claim the event fingerprint. Returns false when a notification
     * for the same fingerprint was already produced within the TTL window.
     */
    protected function claim(PlatformNotificationData $data): bool
    {
        $ttl = (int) config('notifications.platform.dedupe_ttl', 3600);

        if ($ttl <= 0) {
            return true;
        }

        return Cache::add($data->fingerprint(), true, $ttl);
    }
}
