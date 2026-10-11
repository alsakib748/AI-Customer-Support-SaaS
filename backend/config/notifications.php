<?php

/*
|--------------------------------------------------------------------------
| Platform Notifications configuration
|--------------------------------------------------------------------------
|
| The Notifications module is the central inbox for Super Admins. It reuses
| Laravel's database notifications table (the source of truth) and layers a
| normalized payload on top. Everything that tunes platform notification
| behaviour lives here so producers, the API and retention jobs stay in sync.
|
*/

return [

    'platform' => [

        'enabled' => env('PLATFORM_NOTIFICATIONS_ENABLED', true),

        /*
        |------------------------------------------------------------------
        | Deduplication
        |------------------------------------------------------------------
        | Repeated events (queue retries, webhook replays, incident updates)
        | carry a stable fingerprint. A notification for the same fingerprint
        | is suppressed for this many seconds so retries never create duplicate
        | inbox entries. Set to 0 to disable deduplication entirely.
        */
        'dedupe_ttl' => (int) env('PLATFORM_NOTIFICATIONS_DEDUPE_TTL', 3600),

        /*
        |------------------------------------------------------------------
        | Pagination
        |------------------------------------------------------------------
        */
        'per_page'     => (int) env('PLATFORM_NOTIFICATIONS_PER_PAGE', 20),
        'max_per_page' => (int) env('PLATFORM_NOTIFICATIONS_MAX_PER_PAGE', 100),

        /*
        |------------------------------------------------------------------
        | Retention
        |------------------------------------------------------------------
        | Read platform notifications older than this many days are pruned in
        | scheduled batches. Security/audit records keep their own retention.
        */
        'retention_days' => (int) env('PLATFORM_NOTIFICATIONS_RETENTION_DAYS', 90),

        /*
        |------------------------------------------------------------------
        | Categories
        |------------------------------------------------------------------
        | An allow-list of categories that may produce platform notifications.
        | Set a category to false to mute it globally without code changes.
        */
        'categories' => [
            'system_health'   => true,
            'billing'         => true,
            'tenant'          => true,
            'security'        => true,
            'ai_integrations' => true,
        ],

        /*
        |------------------------------------------------------------------
        | Audited actions -> Security notifications
        |------------------------------------------------------------------
        | Actions recorded by AuditLogService that should also raise a
        | Security notification for Super Admins. Any action not listed here
        | stays audit-only. Keyed by the audit action; the value controls the
        | notification severity, title and deep-link.
        */
        'audited_actions' => [
            'settings.security.updated'    => ['severity' => 'warning', 'title' => 'Security settings changed', 'action_url' => '/admin/settings'],
            'settings.security.reset'      => ['severity' => 'warning', 'title' => 'Security settings reset', 'action_url' => '/admin/settings'],
            'settings.credential_rotated'  => ['severity' => 'warning', 'title' => 'Credential rotated', 'action_url' => '/admin/settings'],
            'settings.integrations.updated' => ['severity' => 'info', 'title' => 'Integration settings changed', 'action_url' => '/admin/settings'],
            'settings.integrations.reset'  => ['severity' => 'info', 'title' => 'Integration settings reset', 'action_url' => '/admin/settings'],
            'settings.operations.updated'  => ['severity' => 'warning', 'title' => 'Operations settings changed', 'action_url' => '/admin/settings'],
            'settings.operations.reset'    => ['severity' => 'warning', 'title' => 'Operations settings reset', 'action_url' => '/admin/settings'],
            'settings.maintenance_enabled' => ['severity' => 'warning', 'title' => 'Maintenance mode enabled', 'action_url' => '/admin/settings'],
            'settings.maintenance_disabled' => ['severity' => 'info', 'title' => 'Maintenance mode disabled', 'action_url' => '/admin/settings'],
            'settings.general.updated'     => ['severity' => 'info', 'title' => 'General settings changed', 'action_url' => '/admin/settings'],
            'settings.general.reset'       => ['severity' => 'info', 'title' => 'General settings reset', 'action_url' => '/admin/settings'],
            'settings.ai.updated'          => ['severity' => 'info', 'title' => 'AI defaults changed', 'action_url' => '/admin/settings'],
            'settings.ai.reset'            => ['severity' => 'info', 'title' => 'AI defaults reset', 'action_url' => '/admin/settings'],
            'settings.email.updated'       => ['severity' => 'info', 'title' => 'Email settings changed', 'action_url' => '/admin/settings'],
            'settings.email.reset'         => ['severity' => 'info', 'title' => 'Email settings reset', 'action_url' => '/admin/settings'],

            'user.suspended'        => ['severity' => 'warning', 'title' => 'User suspended', 'action_url' => '/admin/users'],
            'user.sessions_revoked' => ['severity' => 'warning', 'title' => 'User sessions revoked', 'action_url' => '/admin/users'],
            'user.email_changed'    => ['severity' => 'warning', 'title' => 'User email changed', 'action_url' => '/admin/users'],
            'user.deleted'          => ['severity' => 'warning', 'title' => 'User deleted', 'action_url' => '/admin/users'],

            'role_assigned'       => ['severity' => 'warning', 'title' => 'Role assigned', 'action_url' => '/admin/audit-logs'],
            'role_removed'        => ['severity' => 'warning', 'title' => 'Role removed', 'action_url' => '/admin/audit-logs'],
            'permission_granted'  => ['severity' => 'warning', 'title' => 'Permission granted', 'action_url' => '/admin/audit-logs'],
            'permission_revoked'  => ['severity' => 'warning', 'title' => 'Permission revoked', 'action_url' => '/admin/audit-logs'],
        ],
    ],

];
