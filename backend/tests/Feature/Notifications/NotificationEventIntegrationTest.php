<?php

namespace Tests\Feature\Notifications;

use App\Events\Health\PlatformIncidentOpened;
use App\Events\Health\PlatformIncidentResolved;
use App\Health\Enums\IncidentSeverity;
use App\Health\Enums\IncidentStatus;
use App\Models\PlatformIncident;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

/**
 * Verifies the event -> listener -> service wiring produces a persisted
 * platform notification. Uses the health domain events because they are
 * self-contained (no tenancy/bootstrap required).
 */
class NotificationEventIntegrationTest extends TestCase
{
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCentralNotificationSchema();
        $this->createPlatformIncidentSchema();
        $this->ensureSuperAdminRole();
        Cache::flush();
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function incident(array $overrides = []): PlatformIncident
    {
        return PlatformIncident::query()->create(array_merge([
            'fingerprint' => 'fp-'.uniqid(),
            'title'       => 'Primary database unhealthy',
            'summary'     => 'Connection to the primary database is failing.',
            'severity'    => IncidentSeverity::Critical,
            'status'      => IncidentStatus::Open,
            'source'      => 'health_check',
            'started_at'  => now(),
        ], $overrides));
    }

    public function test_incident_opened_event_creates_a_system_health_notification(): void
    {
        $admin = $this->makeSuperAdmin();
        $incident = $this->incident();

        PlatformIncidentOpened::dispatch($incident);

        $this->assertSame(1, $admin->fresh()->notifications()->count());

        $data = $admin->fresh()->notifications()->first()->data;
        $this->assertSame('system_health', $data['category']);
        $this->assertSame('critical', $data['severity']);
        $this->assertSame('health.incident.opened', $data['event_key']);
        $this->assertSame(
            '/admin/system-health/incidents/'.$incident->id,
            $data['action_url'],
        );
    }

    public function test_repeated_incident_opened_events_are_deduplicated(): void
    {
        $admin = $this->makeSuperAdmin();
        $incident = $this->incident();

        PlatformIncidentOpened::dispatch($incident);
        PlatformIncidentOpened::dispatch($incident);

        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_incident_resolved_event_creates_an_info_notification(): void
    {
        $admin = $this->makeSuperAdmin();
        $incident = $this->incident([
            'status'      => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ]);

        PlatformIncidentResolved::dispatch($incident);

        $data = $admin->fresh()->notifications()->first()->data;
        $this->assertSame('health.incident.resolved', $data['event_key']);
        $this->assertSame('system_health', $data['category']);
        $this->assertSame('info', $data['severity']);
    }
}
