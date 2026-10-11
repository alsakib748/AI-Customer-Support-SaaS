<?php

namespace Tests\Feature\Billing;

use App\Jobs\Billing\ProcessBillingWebhookJob;
use App\Jobs\Billing\SendRenewalReminders;
use App\Jobs\Billing\SyncSubscriptionFromProviderJob;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\Billing\RenewalReminderNotification;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Services\Billing\BillingWebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class BillingJobsTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');

        $this->createCentralSchema();
        $this->createDefaultPlan();
    }

    protected function createTenantWithOwner(): array
    {
        $tenantId = (string) Str::uuid();

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $owner = User::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'owner'.uniqid().'@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
        ]);

        TenantUser::create([
            'tenant_id' => $tenantId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        [$tenant] = $this->createTenantWithOwner();
        $plan = $this->createDefaultPlan();

        return Subscription::create(array_merge([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'auto_renew' => true,
            'next_billing_at' => now()->addDays(7),
        ], $overrides));
    }

    public function test_send_renewal_reminders_notifies_the_tenant_owner(): void
    {
        Notification::fake();

        [$tenant, $owner] = $this->createTenantWithOwner();
        $plan = $this->createDefaultPlan();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'auto_renew' => true,
            'next_billing_at' => now()->addDays(7)->setTime(12, 0),
        ]);

        (new SendRenewalReminders)->handle();

        Notification::assertSentTo($owner, RenewalReminderNotification::class);
    }

    public function test_send_renewal_reminders_ignores_subscriptions_that_do_not_renew(): void
    {
        Notification::fake();

        [$tenant, $owner] = $this->createTenantWithOwner();
        $plan = $this->createDefaultPlan();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'auto_renew' => false,
            'next_billing_at' => now()->addDays(7)->setTime(12, 0),
        ]);

        (new SendRenewalReminders)->handle();

        Notification::assertNotSentTo($owner, RenewalReminderNotification::class);
    }

    public function test_sync_subscription_from_provider_updates_status_and_logs_the_previous_one(): void
    {
        Log::spy();

        $subscription = $this->createSubscription([
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_remote_1',
            'status' => 'active',
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('getSubscription')
            ->once()
            ->with('sub_remote_1')
            ->andReturn(['status' => 'past_due']);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->once()->with('stripe')->andReturn($gateway);

        (new SyncSubscriptionFromProviderJob($subscription->id))->handle($manager);

        $this->assertSame('past_due', $subscription->fresh()->status);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context) => $message === 'Subscription synced from provider'
                && $context['old_status'] === 'active'
                && $context['new_status'] === 'past_due');
    }

    public function test_sync_subscription_is_skipped_for_locally_managed_subscriptions(): void
    {
        $subscription = $this->createSubscription(['provider' => null]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->never();

        (new SyncSubscriptionFromProviderJob($subscription->id))->handle($manager);

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_process_billing_webhook_job_applies_the_event_and_marks_it_processed(): void
    {
        $subscription = $this->createSubscription([
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_remote_2',
            'status' => 'active',
        ]);

        $event = PaymentWebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_1',
            'event_type' => 'customer.subscription.deleted',
            'status' => 'received',
            'payload' => ['id' => 'evt_1'],
            'normalized_payload' => [
                'type' => 'subscription.cancelled',
                'provider' => 'stripe',
                'provider_subscription_id' => 'sub_remote_2',
                'provider_customer_id' => 'cus_1',
                'metadata' => [],
            ],
        ]);

        (new ProcessBillingWebhookJob($event->id))->handle(app(BillingWebhookService::class));

        $this->assertSame('processed', $event->fresh()->status);
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    public function test_the_dead_process_payment_webhook_job_is_gone(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Jobs/Billing/ProcessPaymentWebhook.php'),
            'the broken, unreferenced ProcessPaymentWebhook job must be removed'
        );
    }
}
