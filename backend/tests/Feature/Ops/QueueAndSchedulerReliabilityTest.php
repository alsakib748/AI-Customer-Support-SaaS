<?php

namespace Tests\Feature\Ops;

use App\Jobs\Billing\ProcessBillingWebhookJob;
use App\Jobs\Billing\ProcessExpiredSubscriptions;
use App\Jobs\Billing\ProcessSubscriptionRenewals;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class QueueAndSchedulerReliabilityTest extends TestCase
{
    public function test_redis_queue_connection_defines_queue_exactly_once(): void
    {
        $source = file_get_contents(config_path('queue.php'));

        $this->assertIsString($source);
        $this->assertSame(1, preg_match('/\'redis\'\s*=>\s*\[(.*?)\n        \],/s', $source, $matches), 'redis queue block not found');

        $this->assertSame(
            1,
            substr_count($matches[1], "'queue' =>"),
            'the redis queue connection must not define a duplicate "queue" key',
        );
    }

    public function test_batching_and_failed_jobs_use_the_application_database_connection(): void
    {
        $default = config('database.default');

        $this->assertSame($default, config('queue.batching.database'));
        $this->assertSame($default, config('queue.failed.database'));
    }

    public function test_widget_cleanup_schedules_prevent_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) ($event->command ?? ''), 'widget:cleanup-sessions'));

        $this->assertCount(2, $events, 'both widget cleanup schedules must be registered');

        foreach ($events as $event) {
            $this->assertTrue(
                $event->withoutOverlapping,
                'widget cleanup schedules must use withoutOverlapping()',
            );
        }
    }

    public function test_subscription_renewals_job_is_retryable(): void
    {
        $job = new ProcessSubscriptionRenewals;

        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }

    public function test_subscription_expiry_job_is_retryable(): void
    {
        $job = new ProcessExpiredSubscriptions;

        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }

    public function test_billing_webhook_job_has_a_timeout(): void
    {
        $job = new ProcessBillingWebhookJob(1);

        $this->assertGreaterThan(0, $job->timeout);
    }
}
