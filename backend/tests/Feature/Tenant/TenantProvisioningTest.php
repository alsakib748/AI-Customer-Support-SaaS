<?php

namespace Tests\Feature\Tenant;

use App\Events\Tenant\TenantProvisioningCompleted;
use App\Events\Tenant\TenantProvisioningFailed;
use App\Events\Tenant\TenantProvisioningStarted;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\JWTAuthMiddleware;
use App\Jobs\Tenant\ProvisionTenantJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

/**
 * Covers the tenant provisioning unit of work: it must be idempotent, run
 * through the queue-backed job, and stay recoverable after a failure.
 */
class TenantProvisioningTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'tenancy.database.central_connection' => 'central',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'cache.default' => 'array',
        ]);

        DB::purge('central');
        DB::purge('tenant');

        $this->createCentralSchema();

        Event::fake([
            TenantProvisioningStarted::class,
            TenantProvisioningCompleted::class,
            TenantProvisioningFailed::class,
        ]);
    }

    protected function makeTenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid(),
            'subdomain' => 'acme-'.uniqid(),
            'status' => Tenant::STATUS_PROVISIONING,
            'data' => [],
        ], $attributes));
    }

    public function test_provision_attaches_owner_creates_trial_subscription_and_marks_provisioned(): void
    {
        $plan = $this->createDefaultPlan();
        $tenant = $this->makeTenant();

        $result = app(TenantProvisioningService::class)->provision($tenant, [
            'owner' => ['email' => 'owner@example.test', 'name' => 'Owner One'],
            'plan_id' => $plan->id,
        ]);

        $this->assertSame(Tenant::STATUS_TRIAL, $result->status);
        $this->assertNotNull($result->provisioned_at);

        $membership = $result->ownerMembership()->first();
        $this->assertNotNull($membership, 'the owner membership must be created');
        $this->assertSame('owner@example.test', $membership->user->email);

        $subscription = $result->subscriptions()->first();
        $this->assertNotNull($subscription, 'a trial subscription must be created');
        $this->assertSame('trialing', $subscription->status);
        $this->assertSame($plan->id, $subscription->plan_id);
    }

    public function test_provision_is_idempotent_when_run_twice(): void
    {
        $plan = $this->createDefaultPlan();
        $tenant = $this->makeTenant();
        $service = app(TenantProvisioningService::class);

        $data = [
            'owner' => ['email' => 'owner@example.test', 'name' => 'Owner One'],
            'plan_id' => $plan->id,
        ];

        $service->provision($tenant, $data);
        $service->provision($tenant->fresh(), $data);

        $this->assertSame(1, $tenant->ownerMembership()->count());
        $this->assertSame(1, $tenant->subscriptions()->count());
        $this->assertSame(
            1,
            DB::connection('central')->table('users')->where('email', 'owner@example.test')->count(),
            'the owner user must not be duplicated on retry',
        );
    }

    public function test_retry_recovers_a_failed_tenant_without_requiring_the_owner_payload(): void
    {
        $this->createDefaultPlan();
        $tenant = $this->makeTenant([
            'status' => Tenant::STATUS_PROVISIONING_FAILED,
            'provisioning_error' => 'database unavailable',
        ]);

        $owner = User::create([
            'first_name' => 'Owner',
            'last_name' => 'One',
            'username' => 'owner-one',
            'email' => 'owner@example.test',
            'password' => 'secret',
            'uuid' => '11111111-1111-1111-1111-111111111111',
        ]);

        $tenant->ownerMembership()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $result = app(TenantProvisioningService::class)->retry($tenant);

        $this->assertSame(Tenant::STATUS_TRIAL, $result->fresh()->status);
        $this->assertNull($result->fresh()->provisioning_error);
        $this->assertSame(1, $tenant->subscriptions()->count());
    }

    public function test_retry_dispatches_the_queue_backed_job(): void
    {
        Queue::fake();

        $tenant = $this->makeTenant(['status' => Tenant::STATUS_PROVISIONING_FAILED]);

        app(TenantProvisioningService::class)->retry($tenant);

        Queue::assertPushed(
            ProvisionTenantJob::class,
            fn (ProvisionTenantJob $job) => $job->tenant->id === $tenant->id
        );
    }

    public function test_retry_rejects_tenants_that_did_not_fail(): void
    {
        $tenant = $this->makeTenant(['status' => Tenant::STATUS_TRIAL]);

        $this->expectException(\RuntimeException::class);

        app(TenantProvisioningService::class)->retry($tenant);
    }

    public function test_provision_records_failure_state_when_a_step_throws(): void
    {
        $tenant = $this->makeTenant();

        try {
            app(TenantProvisioningService::class)->provision($tenant, [
                'owner' => ['email' => 'not-an-email'],
            ]);
            $this->fail('provisioning should have thrown for an invalid owner email');
        } catch (\Throwable $e) {
            // expected
        }

        $failed = $tenant->fresh();
        $this->assertSame(Tenant::STATUS_PROVISIONING_FAILED, $tenant->fresh()->status);
        $this->assertNotNull($failed->provisioning_error);
    }

    public function test_job_failed_handler_marks_the_tenant_as_failed(): void
    {
        $tenant = $this->makeTenant(['status' => Tenant::STATUS_PROVISIONING]);

        (new ProvisionTenantJob($tenant))->failed(new \RuntimeException('retries exhausted'));

        $fresh = $tenant->fresh();
        $this->assertSame(Tenant::STATUS_PROVISIONING_FAILED, $fresh->status);
        $this->assertSame('retries exhausted', $fresh->provisioning_error);
    }

    public function test_admin_store_provisions_the_tenant_through_the_job(): void
    {
        $plan = $this->createDefaultPlan();
        $admin = $this->makeSuperAdmin();

        $this->withoutMiddleware([JWTAuthMiddleware::class, EnsureSuperAdmin::class]);
        $this->actingAs($admin, 'api');

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Integration Co',
            'owner' => ['email' => 'boss@example.test', 'name' => 'Boss Person'],
            'plan_id' => $plan->id,
        ]);

        $response->assertStatus(201);

        $tenant = Tenant::query()->where('name', 'Integration Co')->firstOrFail();
        $this->assertSame(Tenant::STATUS_TRIAL, $tenant->status);
        $this->assertNotNull($tenant->provisioned_at);
        $this->assertSame('boss@example.test', $tenant->ownerMembership->user->email);
        $this->assertSame('trialing', $tenant->subscriptions()->first()->status);
    }
}
