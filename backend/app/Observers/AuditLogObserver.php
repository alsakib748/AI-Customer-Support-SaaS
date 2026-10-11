<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the existing audit trail to the platform inbox. Actions that
 * represent a significant security event or a privileged configuration
 * change (allow-listed in config/notifications.php) surface as Security
 * notifications for Super Admins. Everything else stays audit-only.
 */
class AuditLogObserver
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function created(AuditLog $log): void
    {
        $action = (string) $log->action;
        $actions = config('notifications.platform.audited_actions', []);
        $definition = is_array($actions) ? ($actions[$action] ?? null) : null;

        if (! is_array($definition)) {
            return;
        }

        try {
            $actor = $log->user_name;
            $title = $definition['title'] ?? $log->action_label;
            $severity = NotificationSeverity::tryFrom($definition['severity'] ?? 'info')
                ?? NotificationSeverity::Info;

            $this->notifications->send(new PlatformNotificationData(
                eventKey: 'security.'.$action,
                category: NotificationCategory::Security,
                severity: $severity,
                title: $title,
                message: sprintf('%s by %s.', $title, $actor),
                actionUrl: $definition['action_url'] ?? '/admin/audit-logs',
                entityType: 'audit_log',
                entityId: (string) $log->id,
                meta: [
                    'action'        => $action,
                    'actor_id'      => $log->user_id,
                    'actor_name'    => $actor,
                    'resource_type' => $log->resource_type,
                    'resource_id'   => $log->resource_id,
                    'group'         => $log->metadata['group'] ?? null,
                ],
                occurredAt: $log->created_at ?? now(),
                dedupeKey: 'platform-notify:audit:'.$log->id,
            ));
        } catch (\Throwable $e) {
            // Notification bookkeeping must never break audit logging.
            Log::error('Failed to create platform notification for audit log.', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
