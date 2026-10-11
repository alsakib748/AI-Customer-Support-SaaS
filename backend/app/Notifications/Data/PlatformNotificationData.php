<?php

namespace App\Notifications\Data;

use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Provider-agnostic description of a platform notification. This is the shared
 * contract between domain events, the PlatformNotificationService and the
 * persisted database notification payload (see PlatformNotification::toArray).
 */
final readonly class PlatformNotificationData
{
    public function __construct(
        public string $eventKey,
        public NotificationCategory $category,
        public NotificationSeverity $severity,
        public string $title,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public array $meta = [],
        public ?DateTimeInterface $occurredAt = null,
        public ?string $dedupeKey = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scope'       => 'platform',
            'event_key'   => $this->eventKey,
            'category'    => $this->category->value,
            'severity'    => $this->severity->value,
            'title'       => $this->title,
            'message'     => $this->message,
            'action_url'  => $this->actionUrl,
            'entity_type' => $this->entityType,
            'entity_id'   => $this->entityId,
            'meta'        => $this->meta,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }

    public function occurredAtIso(): string
    {
        $occurredAt = $this->occurredAt !== null
            ? Carbon::instance($this->occurredAt)
            : Carbon::now();

        return $occurredAt->toIso8601String();
    }

    /**
     * Stable key used to deduplicate repeated dispatches of the same event.
     */
    public function fingerprint(): string
    {
        if ($this->dedupeKey !== null && $this->dedupeKey !== '') {
            return $this->dedupeKey;
        }

        $entity = ($this->entityType && $this->entityId)
            ? ':'.$this->entityType.':'.$this->entityId
            : '';

        return 'platform-notify:'.sha1($this->eventKey.$entity);
    }
}
