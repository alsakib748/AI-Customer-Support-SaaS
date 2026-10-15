<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingDunningService;
use App\Services\Tenant\TenantLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingDunningServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.grace_period.past_due_days' => 3,
            'billing.grace_period.suspend_days' => 7,
            'billing.grace_period.cancel_days' => 30,
            'tenancy.database.central_connection' => 'central',
        ]);

        $this->migrateCentralSchema();

        $this->mock(TenantLifecycleService::class, function ($mock) {
            $mock->shouldReceive('suspend')->andReturnUsing(function ($tenant, $reason = null) {
                $tenant->update([
                    'status' => 'suspended',
                    'suspension_reason' => $reason,
                    'suspended_at' => now(),
                ]);

                return $tenant->fresh();
            });

            $mock->shouldReceive('activate')->andReturnUsing(function ($tenant) {
                $tenant->update([
                    'status' => 'active',
                    'suspension_reason' => null,
                    'suspended_at' => null,
                ]);

                return $tenant->fresh();
            });
        });
    }

    protected function migrateCentralSchema(): void
    {
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('tenant_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->string('billing_cycle')->default('monthly');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('past_due_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('provider')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('tenant_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('due_at')->nullable();
            $table->string('currency')->default('USD');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function makeTenant(array $overrides = []): Tenant
    {
        $id = $overrides['id'] ?? (string) Str::uuid();

        DB::connection('central')->table('tenants')->insert(array_merge([
            'id' => $id,
            'name' => 'Acme Inc',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides, ['id' => $id]));

        return Tenant::on('central')->findOrFail($id);
    }

    protected function makeSubscription(array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'tenant_id' => $this->makeTenant()->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'auto_renew' => true,
        ], $overrides));
    }

    protected function makeOverdueInvoice(Subscription $subscription, int $daysAgo, string $status = 'open'): Invoice
    {
        return Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-'.uniqid(),
            'status' => $status,
            'due_at' => now()->subDays($daysAgo),
            'currency' => 'USD',
            'total' => 100,
        ]);
    }

    protected function dunning(): BillingDunningService
    {
        return $this->app->make(BillingDunningService::class);
    }

    public function test_overdue_invoice_marks_subscription_past_due_after_grace(): void
    {
        $subscription = $this->makeSubscription();
        $this->makeOverdueInvoice($subscription, 10);

        $count = $this->dunning()->markOverdueAsPastDue();

        $this->assertSame(1, $count);
        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);
        $this->assertNotNull($subscription->past_due_at);
    }

    public function test_recent_overdue_invoice_does_not_mark_past_due(): void
    {
        $subscription = $this->makeSubscription();
        $this->makeOverdueInvoice($subscription, 1);

        $count = $this->dunning()->markOverdueAsPastDue();

        $this->assertSame(0, $count);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_paid_invoice_does_not_mark_past_due(): void
    {
        $subscription = $this->makeSubscription();
        $this->makeOverdueInvoice($subscription, 10, 'paid');

        $count = $this->dunning()->markOverdueAsPastDue();

        $this->assertSame(0, $count);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_delinquent_subscription_and_tenant_are_suspended_after_suspend_days(): void
    {
        $tenant = $this->makeTenant();
        $subscription = $this->makeSubscription(['tenant_id' => $tenant->id, 'status' => 'past_due', 'past_due_at' => now()->subDays(10)]);
        $this->makeOverdueInvoice($subscription, 10);

        $count = $this->dunning()->suspendDelinquentSubscriptions();

        $this->assertSame(1, $count);

        $subscription->refresh();
        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspension_reason);

        $tenant->refresh();
        $this->assertSame('suspended', $tenant->status);
        $this->assertStringStartsWith(BillingDunningService::SUSPENSION_PREFIX, (string) $tenant->suspension_reason);
    }

    public function test_recently_past_due_subscription_is_not_suspended(): void
    {
        $tenant = $this->makeTenant();
        $subscription = $this->makeSubscription(['tenant_id' => $tenant->id, 'status' => 'past_due', 'past_due_at' => now()->subDays(2)]);
        $this->makeOverdueInvoice($subscription, 2);

        $count = $this->dunning()->suspendDelinquentSubscriptions();

        $this->assertSame(0, $count);
        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_extended_delinquency_expires_subscription_after_cancel_days(): void
    {
        $tenant = $this->makeTenant(['status' => 'suspended', 'suspension_reason' => 'billing:subscription_1']);
        $subscription = $this->makeSubscription(['tenant_id' => $tenant->id, 'status' => 'suspended', 'past_due_at' => now()->subDays(40)]);
        $this->makeOverdueInvoice($subscription, 40);

        $count = $this->dunning()->cancelDelinquentSubscriptions();

        $this->assertSame(1, $count);
        $this->assertSame('expired', $subscription->fresh()->status);
        // Tenant stays suspended (data retained) until payment is recovered.
        $this->assertSame('suspended', $tenant->fresh()->status);
    }

    public function test_recovered_payment_reactivates_subscription_and_tenant(): void
    {
        $tenant = $this->makeTenant(['status' => 'suspended', 'suspension_reason' => 'billing:subscription_1', 'suspended_at' => now()]);
        $subscription = $this->makeSubscription(['tenant_id' => $tenant->id, 'status' => 'suspended', 'suspended_at' => now(), 'past_due_at' => now()->subDays(10), 'suspension_reason' => 'billing:subscription_1']);
        $this->makeOverdueInvoice($subscription, 10, 'paid');

        $changed = $this->dunning()->reactivateForRecoveredPayment($subscription);

        $this->assertTrue($changed);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->suspension_reason);
    }

    public function test_recovered_payment_keeps_suspension_while_still_owed(): void
    {
        $tenant = $this->makeTenant(['status' => 'suspended', 'suspension_reason' => 'billing:subscription_1', 'suspended_at' => now()]);
        $subscription = $this->makeSubscription(['tenant_id' => $tenant->id, 'status' => 'suspended', 'suspended_at' => now(), 'past_due_at' => now()->subDays(10)]);
        $this->makeOverdueInvoice($subscription, 10); // still open/overdue

        $changed = $this->dunning()->reactivateForRecoveredPayment($subscription);

        $this->assertFalse($changed);
        $this->assertSame('suspended', $tenant->fresh()->status);
    }

    public function test_provider_managed_subscription_is_detected(): void
    {
        $local = $this->makeSubscription(['provider' => null, 'provider_subscription_id' => null]);
        $this->assertFalse($local->isProviderManaged());

        $remote = $this->makeSubscription(['provider' => 'stripe', 'provider_subscription_id' => 'sub_123']);
        $this->assertTrue($remote->isProviderManaged());
    }

    public function test_dunning_summary_reports_counts(): void
    {
        $subscription = $this->makeSubscription();
        $this->makeOverdueInvoice($subscription, 10);

        $summary = $this->dunning()->enforce();

        $this->assertSame(1, $summary['past_due']);
        $this->assertArrayHasKey('suspended', $summary);
        $this->assertArrayHasKey('cancelled', $summary);
    }
}
