<?php

namespace App\Http\Resources\Admin;

use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Normalizes a database notification (new or legacy) into the stable payload
 * the Vue inbox consumes. Older payloads that only carry "type"/"message" are
 * mapped to a category, severity and title instead of being discarded.
 */
class PlatformNotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->decodeData($this->data);
        $legacyType = $data['type'] ?? $this->type ?? null;

        $category = $this->resolveCategory($data, $legacyType);
        $severity = $this->resolveSeverity($data, $legacyType);

        return [
            'id'             => (string) $this->id,
            'type'           => $this->type,
            'event_key'      => $data['event_key'] ?? $legacyType,
            'category'       => $category->value,
            'category_label' => $category->label(),
            'category_icon'  => $category->icon(),
            'severity'       => $severity->value,
            'severity_label' => $severity->label(),
            'severity_tone'  => $severity->tone(),
            'title'          => $this->resolveTitle($data, $legacyType, $category),
            'message'        => (string) ($data['message'] ?? ''),
            'action_url'     => $this->safeActionUrl($data['action_url'] ?? null),
            'entity_type'    => $data['entity_type'] ?? null,
            'entity_id'      => isset($data['entity_id']) ? (string) $data['entity_id'] : null,
            'meta'           => is_array($data['meta'] ?? null) ? $data['meta'] : [],
            'read_at'        => optional($this->read_at)->toIso8601String(),
            'created_at'     => optional($this->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function resolveCategory(array $data, ?string $legacyType): NotificationCategory
    {
        $category = $data['category'] ?? null;

        if (is_string($category)) {
            $resolved = NotificationCategory::tryFrom($category);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return NotificationCategory::fromLegacyType($legacyType);
    }

    protected function resolveSeverity(array $data, ?string $legacyType): NotificationSeverity
    {
        $severity = $data['severity'] ?? null;

        if (is_string($severity)) {
            $resolved = NotificationSeverity::tryFrom($severity);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $type = strtolower((string) $legacyType);

        return match (true) {
            str_contains($type, 'failed'),
            str_contains($type, 'error')      => NotificationSeverity::Error,
            str_contains($type, 'expired'),
            str_contains($type, 'cancelled'),
            str_contains($type, 'overdue')    => NotificationSeverity::Warning,
            default                           => NotificationSeverity::Info,
        };
    }

    protected function resolveTitle(array $data, ?string $legacyType, NotificationCategory $category): string
    {
        $title = $data['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }

        if (is_string($legacyType) && $legacyType !== '') {
            return Str::headline($legacyType);
        }

        return $category->label();
    }

    /**
     * Only allow internal, relative paths. Rejects protocol-relative,
     * absolute and scheme-smuggled URLs so a malformed payload can never
     * redirect an administrator off-platform.
     */
    protected function safeActionUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (! preg_match('#^/[A-Za-z0-9/_\-.?=&%]*$#', $url)) {
            return null;
        }

        return $url;
    }
}
