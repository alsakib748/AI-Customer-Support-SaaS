<?php

namespace Tests\Feature\Notifications;

use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

class PlatformNotificationServiceTest extends TestCase
{
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCentralNotificationSchema();
        $this->ensureSuperAdminRole();
        Cache::flush();
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function service(): PlatformNotificationService
    {
        return $this->app->make(PlatformNotificationService::class);
    }

    protected function data(array $overrides = []): PlatformNotificationData
    {
        return new PlatformNotificationData(
            eventKey: $overrides['eventKey'] ?? 'billing.payment_failed',
            category: $overrides['category'] ?? NotificationCategory::Billing,
            severity: $overrides['severity'] ?? NotificationSeverity::Error,
            title: $overrides['title'] ?? 'Payment failed',
            message: $overrides['message'] ?? 'A payment failed.',
            actionUrl: $overrides['actionUrl'] ?? '/admin/billing/payments',
            entityType: $overrides['entityType'] ?? 'payment',
            entityId: $overrides['entityId'] ?? '42',
            dedupeKey: $overrides['dedupeKey'] ?? null,
        );
    }

    public function test_sends_one_database_notification_per_super_admin(): void
    {
        $adminA = $this->makeSuperAdmin(['email' => 'a@example.test']);
        $adminB = $this->makeSuperAdmin(['email' => 'b@example.test']);
        $tenantUser = $this->makeUser(['email' => 'c@example.test']);

        $count = $this->service()->send($this->data(['dedupeKey' => 'evt-1']));

        $this->assertSame(2, $count);
        $this->assertSame(2, DB::connection('central')->table('notifications')->count());

        $a = $adminA->fresh()->notifications()->first();
        $this->assertNotNull($a);
        $this->assertSame(NotificationCategory::Billing->value, $a->data['category']);
        $this->assertSame('platform', $a->data['scope']);
        $this->assertSame('/admin/billing/payments', $a->data['action_url']);

        $this->assertSame(0, $tenantUser->fresh()->notifications()->count());
        $this->assertSame(1, $adminB->fresh()->notifications()->count());
    }

    public function test_deduplicates_repeated_events_with_same_fingerprint(): void
    {
        $this->makeSuperAdmin();

        $first = $this->service()->send($this->data(['dedupeKey' => 'same-event']));
        $second = $this->service()->send($this->data(['dedupeKey' => 'same-event']));

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, DB::connection('central')->table('notifications')->count());
    }

    public function test_respects_disabled_optional_category_preference(): void
    {
        $this->makeSuperAdmin([
            'email'       => 'muted@example.test',
            'preferences' => ['notifications' => ['billing' => false]],
        ]);

        $count = $this->service()->send($this->data([
            'category'  => NotificationCategory::Billing,
            'severity'  => NotificationSeverity::Info,
            'dedupeKey' => 'muted-1',
        ]));

        $this->assertSame(0, $count);
        $this->assertSame(0, DB::connection('central')->table('notifications')->count());
    }

    public function test_critical_alerts_ignore_optional_preferences(): void
    {
        $admin = $this->makeSuperAdmin([
            'email'       => 'critical@example.test',
            'preferences' => ['notifications' => ['billing' => false, 'system_health' => false]],
        ]);

        $count = $this->service()->send($this->data([
            'category'  => NotificationCategory::SystemHealth,
            'severity'  => NotificationSeverity::Critical,
            'eventKey'  => 'health.incident.opened',
            'dedupeKey' => 'critical-1',
        ]));

        $this->assertSame(1, $count);
        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_globally_disabled_category_sends_nothing(): void
    {
        $this->makeSuperAdmin();
        config(['notifications.platform.categories.tenant' => false]);

        $count = $this->service()->send($this->data([
            'category'  => NotificationCategory::Tenant,
            'eventKey'  => 'tenant.suspended',
            'dedupeKey' => 'tenant-1',
        ]));

        $this->assertSame(0, $count);
        $this->assertSame(0, DB::connection('central')->table('notifications')->count());
    }

    public function test_no_super_admins_means_no_notification(): void
    {
        $this->makeUser(['email' => 'plain@example.test']);

        $count = $this->service()->send($this->data(['dedupeKey' => 'no-admins']));

        $this->assertSame(0, $count);
        $this->assertSame(0, DB::connection('central')->table('notifications')->count());
    }

    public function test_inactive_super_admins_are_skipped(): void
    {
        $this->makeSuperAdmin(['email' => 'active@example.test', 'is_active' => true]);
        $this->makeSuperAdmin(['email' => 'inactive@example.test', 'is_active' => false]);

        $count = $this->service()->send($this->data(['dedupeKey' => 'active-only']));

        $this->assertSame(1, $count);
    }
}
