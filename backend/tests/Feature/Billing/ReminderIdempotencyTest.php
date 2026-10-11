<?php

namespace Tests\Feature\Billing;

use App\Jobs\Billing\SendRenewalReminders;
use App\Jobs\Billing\SendTrialEndingReminder;
use App\Models\BillingReminder;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\Billing\RenewalReminderNotification;
use App\Notifications\Billing\TrialEndingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class ReminderIdempotencyTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected Tenant $tenant;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');

        $this->createCentralSchema();

        Notification::fake();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $this->owner = User::create([
            'email' => 'owner@example.test',
            'first_name' => 'Olivia',
            'last_name' => 'Owner',
            'password' => 'secret',
        ]);

        TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);
    }

    protected function subscription(array $attributes): Subscription
    {
        $plan = $this->createDefaultPlan(['name' => 'Pro', 'price_monthly' => 29]);

        return Subscription::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'auto_renew' => true,
        ], $attributes));
    }

    public function test_renewal_reminder_is_sent_once_across_runs(): void
    {
        $this->subscription([
            'next_billing_at' => now()->addDays(7)->setTime(12, 0),
        ]);

        (new SendRenewalReminders())->handle();
        (new SendRenewalReminders())->handle();

        Notification::assertSentToTimes($this->owner, RenewalReminderNotification::class, 1);
        $this->assertSame(1, BillingReminder::where('type', 'renewal')->count());
    }

    public function test_renewal_reminder_falls_back_to_period_end(): void
    {
        $this->subscription([
            'next_billing_at' => null,
            'current_period_ends_at' => now()->addDays(3)->setTime(12, 0),
        ]);

        (new SendRenewalReminders())->handle();

        Notification::assertSentToTimes($this->owner, RenewalReminderNotification::class, 1);
    }

    public function test_trial_reminder_is_sent_once_across_runs(): void
    {
        $this->subscription([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(3)->setTime(12, 0),
        ]);

        (new SendTrialEndingReminder())->handle();
        (new SendTrialEndingReminder())->handle();

        Notification::assertSentToTimes($this->owner, TrialEndingNotification::class, 1);
        $this->assertSame(1, BillingReminder::where('type', 'trial')->count());
    }
}
