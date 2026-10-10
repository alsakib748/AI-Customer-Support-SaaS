<?php

namespace Tests\Feature\Health;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Health\Enums\IncidentSeverity;
use App\Health\Enums\IncidentStatus;
use App\Health\Services\IncidentDetectionService;
use App\Models\PlatformIncident;
use App\Models\PlatformIncidentEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncidentDetectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['cache.default' => 'array']);

        Schema::connection('central')->create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::connection('central')->create('platform_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 120)->index();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('severity', 20)->index();
            $table->string('status', 20)->index();
            $table->string('source', 60)->index();
            $table->datetime('started_at')->index();
            $table->datetime('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->datetime('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('central')->create('platform_incident_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->string('event', 20)->index();
            $table->string('severity', 20)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('meta')->nullable();
            $table->datetime('created_at')->index();
            $table->datetime('updated_at')->nullable();
            $table->foreign('incident_id')->references('id')->on('platform_incidents')->cascadeOnDelete();
        });

        config([
            'health.incidents.failure_confirmation'  => 1,
            'health.incidents.recovery_confirmation' => 1,
            'health.incidents.cooldown_seconds'      => 0,
            'health.checks'                          => [
                'db.primary' => ['label' => 'Database', 'critical' => true],
                'cache.run'  => ['label' => 'Cache', 'critical' => false],
            ],
        ]);
    }

    protected function buildResult(string $key, HealthStatus $status): HealthCheckResult
    {
        return HealthCheckResult::make($key, $status, $key.' '.$status->value.' summary');
    }

    protected function svc(): IncidentDetectionService
    {
        return $this->app->make(IncidentDetectionService::class);
    }

    public function test_unhealthy_critical_opens_critical_incident(): void
    {
        $this->svc()->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'scheduled');

        $incident = PlatformIncident::query()->first();
        $this->assertNotNull($incident);
        $this->assertSame('health:db.primary', $incident->fingerprint);
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame(IncidentSeverity::Critical, $incident->severity);
        $this->assertSame('scheduled', $incident->source);
        $this->assertSame(1, $incident->events()->count());
        $this->assertSame('opened', $incident->events()->first()->event);
    }

    public function test_repeated_failure_updates_existing_incident_without_duplicate(): void
    {
        $svc = $this->svc();
        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'scheduled');
        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Degraded)], 'scheduled');

        $this->assertSame(1, PlatformIncident::query()->count());

        $incident = PlatformIncident::query()->first();
        $this->assertSame(IncidentSeverity::Error, $incident->severity);

        $events = PlatformIncidentEvent::query()->where('incident_id', $incident->id)->pluck('event')->all();
        $this->assertContains('opened', $events);
        $this->assertContains('updated', $events);
    }

    public function test_recovery_resolves_and_recurrence_reopens(): void
    {
        $svc = $this->svc();
        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'scheduled');
        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Healthy)], 'scheduled');

        $resolved = PlatformIncident::query()->where('status', 'resolved')->first();
        $this->assertNotNull($resolved);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertTrue($resolved->events()->pluck('event')->contains('resolved'));

        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'scheduled');

        $this->assertSame(2, PlatformIncident::query()->count());
        $this->assertSame(1, PlatformIncident::query()->where('status', 'open')->count());
    }

    public function test_failure_confirmation_threshold_is_respected(): void
    {
        config(['health.incidents.failure_confirmation' => 2]);

        $svc = $this->svc();
        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'scheduled');
        $this->assertSame(0, PlatformIncident::query()->count());

        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'scheduled');
        $this->assertSame(1, PlatformIncident::query()->count());
    }

    public function test_non_critical_failure_is_error_severity(): void
    {
        $this->svc()->evaluate([$this->buildResult('cache.run', HealthStatus::Unhealthy)], 'scheduled');

        $incident = PlatformIncident::query()->first();
        $this->assertSame(IncidentSeverity::Error, $incident->severity);
    }

    public function test_disabled_and_unknown_never_open_incidents(): void
    {
        $this->svc()
            ->evaluate([
                $this->buildResult('db.primary', HealthStatus::Disabled),
                $this->buildResult('cache.run', HealthStatus::Unknown),
            ], 'scheduled');

        $this->assertSame(0, PlatformIncident::query()->count());
    }

    public function test_manual_acknowledge_and_resolve(): void
    {
        $svc = $this->svc();
        $svc->evaluate([$this->buildResult('db.primary', HealthStatus::Unhealthy)], 'manual');

        $incident = PlatformIncident::query()->first();

        $acknowledged = $svc->acknowledge($incident);
        $this->assertSame(IncidentStatus::Acknowledged, $acknowledged->status);
        $this->assertNotNull($acknowledged->acknowledged_at);

        $resolved = $svc->resolve($acknowledged->refresh());
        $this->assertSame(IncidentStatus::Resolved, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);

        $events = $resolved->events()->pluck('event')->all();
        $this->assertContains('opened', $events);
        $this->assertContains('acknowledged', $events);
        $this->assertContains('resolved', $events);
    }
}
