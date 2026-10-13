<?php

use App\Health\Checks\AiProviderHealthCheck;
use App\Health\Checks\ApplicationHealthCheck;
use App\Health\Checks\BackupFreshnessCheck;
use App\Health\Checks\BillingWebhookHealthCheck;
use App\Health\Checks\CacheHealthCheck;
use App\Health\Checks\DatabaseHealthCheck;
use App\Health\Checks\MailHealthCheck;
use App\Health\Checks\QueueHealthCheck;
use App\Health\Checks\SchedulerHealthCheck;
use App\Health\Checks\StorageHealthCheck;

/*
|--------------------------------------------------------------------------
| System Health configuration
|--------------------------------------------------------------------------
|
| This is the single source of truth for the platform monitoring module.
| It declares every check, its criticality and its thresholds, plus the
| retention and incident policies. Thresholds live here (not scattered in
| controllers or the frontend) so the backend and dashboard stay consistent.
|
*/

return [

    'enabled' => env('HEALTH_MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Registered checks
    |--------------------------------------------------------------------------
    | The allow-list of health checks. Order controls display order.
    */
    'check_classes' => [
        ApplicationHealthCheck::class,
        DatabaseHealthCheck::class,
        CacheHealthCheck::class,
        QueueHealthCheck::class,
        SchedulerHealthCheck::class,
        StorageHealthCheck::class,
        MailHealthCheck::class,
        AiProviderHealthCheck::class,
        BackupFreshnessCheck::class,
        BillingWebhookHealthCheck::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-check configuration
    |--------------------------------------------------------------------------
    | key          -> matches HealthCheck::key()
    | label        -> human label shown on the dashboard
    | critical     -> a failure makes the whole platform status critical
    | enabled      -> allow/disallow running the check
    | stale_after_seconds -> optional per-check freshness override; when the
    |                 last stored result is older than this (defaults to the
    |                 global 'stale_after_seconds' below) the check is shown as
    |                 "unknown" instead of claiming an unproven healthy state.
    | ...thresholds-> check-specific tuning consumed by each check
    */
    'checks' => [
        'application.api' => [
            'label' => 'Application',
            'critical' => true,
            'enabled' => true,
        ],

        'database.postgres' => [
            'label' => 'Database',
            'critical' => true,
            'enabled' => true,
            'degraded_latency_ms' => 250,
            'unhealthy_latency_ms' => 2000,
        ],

        'cache.default' => [
            'label' => 'Cache',
            'critical' => false,
            'enabled' => true,
            'degraded_latency_ms' => 50,
        ],

        'queue.default' => [
            'label' => 'Queue',
            'critical' => true,
            'enabled' => true,
            'degraded_pending' => 100,
            'unhealthy_pending' => 1000,
            'degraded_oldest_minutes' => 10,
            'unhealthy_oldest_minutes' => 60,
            'degraded_failed' => 1,
            'unhealthy_failed' => 50,
            'worker_stale_seconds' => 300,
        ],

        'scheduler.heartbeat' => [
            'label' => 'Scheduler',
            'critical' => true,
            'enabled' => true,
            'stale_seconds' => 180,
        ],

        'storage.default' => [
            'label' => 'Storage',
            'critical' => false,
            'enabled' => true,
            'latency_ms' => 1500,
        ],

        'mail.default' => [
            'label' => 'Mail',
            'critical' => false,
            'enabled' => true,
        ],

        'ai.provider' => [
            'label' => 'AI Provider',
            'critical' => false,
            'enabled' => true,
            'probe' => env('HEALTH_AI_PROBE', true),
            'timeout' => env('HEALTH_AI_PROBE_TIMEOUT', 5),
            'probe_paths' => [
                'openai' => 'models',
                'groq' => 'models',
                'anthropic' => 'models',
                'gemini' => 'models',
            ],
        ],

        'billing.webhooks' => [
            'label' => 'Billing Webhooks',
            'critical' => false,
            'enabled' => true,
            'degraded_pending' => 5,
            'unhealthy_pending' => 100,
            'degraded_oldest_minutes' => 15,
            'unhealthy_oldest_minutes' => 120,
            'degraded_failed' => 1,
            'unhealthy_failed' => 25,
        ],

        'backup.freshness' => [
            'label' => 'Backup Freshness',
            'critical' => false,
            'enabled' => env('HEALTH_BACKUP_CHECK_ENABLED', false),
            'marker_path' => env('BACKUP_MARKER_PATH'),
            'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Staleness
    |--------------------------------------------------------------------------
    | A stored result older than this is treated as "unknown" on the dashboard
    | instead of being reported as healthy.
    */
    'stale_after_seconds' => env('HEALTH_STALE_AFTER_SECONDS', 600),

    /*
    |--------------------------------------------------------------------------
    | Heartbeats (cache keys)
    |--------------------------------------------------------------------------
    */
    'heartbeat' => [
        'scheduler_key' => 'health:heartbeat:scheduler',
        'worker_key' => 'health:heartbeat:worker',
        'worker_ttl' => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Incident policy
    |--------------------------------------------------------------------------
    */
    'incidents' => [
        // A condition must be observed this many consecutive times before it
        // opens an incident, and this many times healthy before auto-resolving.
        'failure_confirmation' => (int) env('HEALTH_INCIDENT_FAILURE_CONFIRMATIONS', 2),
        'recovery_confirmation' => (int) env('HEALTH_INCIDENT_RECOVERY_CONFIRMATIONS', 2),
        // Minimum seconds between duplicate incident updates (alert cooldown).
        'cooldown_seconds' => (int) env('HEALTH_INCIDENT_COOLDOWN', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics & retention
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'enabled' => env('HEALTH_METRICS_ENABLED', true),
    ],

    'retention' => [
        'check_results_days' => (int) env('HEALTH_RETENTION_CHECK_DAYS', 30),
        'metric_samples_days' => (int) env('HEALTH_RETENTION_METRIC_DAYS', 30),
        'incident_events_days' => (int) env('HEALTH_RETENTION_EVENT_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | On-demand checks
    |--------------------------------------------------------------------------
    | Rate limit string: "<maxAttempts>,<decayMinutes>".
    */
    'on_demand' => [
        'rate_limit' => env('HEALTH_ON_DEMAND_RATE_LIMIT', '10,1'),
    ],

];
