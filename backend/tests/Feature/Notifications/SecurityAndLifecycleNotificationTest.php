<?php

namespace Tests\Feature\Notifications;

use App\Events\Billing\SubscriptionCancelled;
use App\Events\Tenant\TenantArchived;
use App\Events\Tenant\TenantProvisioningCompleted;
use App\Listeners\Notifications\NotifySubscriptionCancelled;
use App\Listeners\Notifications\NotifyTenantArchived;
use App\Listeners\Notifications\NotifyTenantProvisioningCompleted;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

/**
 * Covers the security/audit, tenant-registration and subscription-cancellation
 * triggers that complete the platform notification feed.
 */
class SecurityAndLifecycleNotificationTest extends TestCase
{
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCentralNotificationSchema();
        $this->createAuditLogSchema();
        $this->ensureSuperAdminRole();
        Cache::flush();
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function fakeTenant(string $id, string $name, string $status): Tenant
    {
        $tenant = new Tenant();
        $tenant->id = $id;
        $tenant->name = $name;
        $tenant->status = $status;

        return $tenant;
    }

    public function test_sensitive_audit_action_creates_a_security_notification(): void
    {
        $admin = $this->makeSuperAdmin();

        AuditLog::create([
            'user_id'       => $admin->id,
            'action'        => 'settings.security.updated',
            'resource_type' => 'system_setting',
            'metadata'      => ['group' => 'security'],
        ]);

        $data = $admin->fresh()->notifications()->first()->data;

        $this->assertSame('security', $data['category']);
        $this->assertSame('warning', $data['severity']);
        $this->assertSame('security.settings.security.updated', $data['event_key']);
        $this->assertSame('/admin/settings', $data['action_url']);
        $this->assertStringContainsString('Security settings changed', $data['title']);
    }

    public function test_non_allowlisted_audit_action_does_not_notify(): void
    {
        $admin = $this->makeSuperAdmin();

        AuditLog::create([
            'user_id'       => $admin->id,
            'action'        => 'customer_created',
            'resource_type' => 'customer',
            'resource_id'   => '5',
        ]);

        $this->assertSame(0, $admin->fresh()->notifications()->count());
    }

    public function test_credential_rotation_raises_a_security_notification(): void
    {
        $admin = $this->makeSuperAdmin();

        AuditLog::create([
            'user_id' => $admin->id,
            'action'  => 'settings.credential_rotated',
        ]);

        $data = $admin->fresh()->notifications()->first()->data;

        $this->assertSame('security', $data['category']);
        $this->assertSame('Credential rotated', $data['title']);
    }

    public function test_tenant_provisioning_completed_creates_a_registration_notification(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->fakeTenant('tenant-1', 'Acme', Tenant::STATUS_TRIAL);

        app(NotifyTenantProvisioningCompleted::class)
            ->handle(new TenantProvisioningCompleted($tenant));

        $data = $admin->fresh()->notifications()->first()->data;

        $this->assertSame('tenant', $data['category']);
        $this->assertSame('info', $data['severity']);
        $this->assertSame('tenant.registered', $data['event_key']);
        $this->assertSame('/admin/tenants/tenant-1', $data['action_url']);
        $this->assertStringContainsString('Acme', $data['message']);
    }

    public function test_tenant_archived_creates_a_warning_notification(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->fakeTenant('tenant-9', 'Legacy Co', Tenant::STATUS_ARCHIVED);

        app(NotifyTenantArchived::class)->handle(new TenantArchived($tenant));

        $data = $admin->fresh()->notifications()->first()->data;

        $this->assertSame('tenant', $data['category']);
        $this->assertSame('warning', $data['severity']);
        $this->assertSame('tenant.archived', $data['event_key']);
    }

    public function test_subscription_cancelled_creates_a_billing_notification(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->fakeTenant('tenant-2', 'Beta Inc', Tenant::STATUS_ACTIVE);

        $subscription = new Subscription();
        $subscription->id = 42;
        $subscription->tenant_id = 'tenant-2';
        $subscription->status = 'cancelled';
        $subscription->setRelation('tenant', $tenant);

        app(NotifySubscriptionCancelled::class)
            ->handle(new SubscriptionCancelled($subscription, true));

        $data = $admin->fresh()->notifications()->first()->data;

        $this->assertSame('billing', $data['category']);
        $this->assertSame('warning', $data['severity']);
        $this->assertSame('billing.subscription_cancelled', $data['event_key']);
        $this->assertStringContainsString('Beta Inc', $data['message']);
    }

    public function test_new_platform_listeners_are_registered(): void
    {
        $provider = new EventServiceProvider($this->app);
        $property = new \ReflectionProperty($provider, 'listen');
        $property->setAccessible(true);
        $listen = $property->getValue($provider);

        $this->assertContains(
            NotifyTenantProvisioningCompleted::class,
            $listen[TenantProvisioningCompleted::class] ?? [],
        );
        $this->assertContains(
            NotifyTenantArchived::class,
            $listen[TenantArchived::class] ?? [],
        );
        $this->assertContains(
            NotifySubscriptionCancelled::class,
            $listen[SubscriptionCancelled::class] ?? [],
        );
    }
}
