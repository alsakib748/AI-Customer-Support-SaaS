<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\PaymentReminderNotification;
use App\Notifications\Billing\SubscriptionSuspendedNotification;
use App\Services\Tenant\TenantLifecycleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Owns the "missed a bill" lifecycle: marking overdue subscriptions past due,
 * suspending access after the grace period, warning the owner by email before
 * that happens, cancelling/expiring after the longer grace period, and
 * restoring access once a payment recovers the account.
 *
 * Every timestamp is derived from the oldest overdue invoice (provider
 * agnostic). Provider-managed subscriptions fall back to the local `past_due_at`
 * marker set by webhooks.
 */
class BillingDunningService
{
    /**
     * Marker prefix used on tenant.suspension_reason so we only auto-restore
     * tenants that we suspended for billing reasons.
     */
    public const SUSPENSION_PREFIX = 'billing:';

    public function __construct(
        protected TenantLifecycleService $tenants,
    ) {}

    /**
     * Run the full dunning pass. Returns a summary of what changed.
     *
     * @return array{past_due:int, suspended:int, cancelled:int}
     */
    public function enforce(): array
    {
        return [
            'past_due' => $this->markOverdueAsPastDue(),
            'suspended' => $this->suspendDelinquentSubscriptions(),
            'cancelled' => $this->cancelDelinquentSubscriptions(),
        ];
    }

    /**
     * Flag active/trialing subscriptions as past_due once an unpaid invoice is
     * overdue by more than `grace_period.past_due_days`.
     */
    public function markOverdueAsPastDue(): int
    {
        $graceDays = (int) config('billing.grace_period.past_due_days', 3);
        $cutoff = now()->subDays($graceDays);
        $count = 0;

        $candidates = Subscription::query()
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->get();

        foreach ($candidates as $subscription) {
            $start = $this->overdueStart($subscription);

            if (! $start) {
                continue;
            }

            if ($subscription->status === 'past_due') {
                // Backfill the aging marker without changing status.
                if (! $subscription->past_due_at) {
                    $subscription->update(['past_due_at' => $start]);
                }

                continue;
            }

            if ($start->lessThanOrEqualTo($cutoff)) {
                $subscription->markAsPastDue();

                Log::info('Subscription marked past_due', [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'overdue_since' => $start->toISOString(),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Suspend access for subscriptions whose oldest overdue invoice exceeds
     * `grace_period.suspend_days`. Suspension is applied at the tenant level so
     * every route behind TenantAware is blocked.
     */
    public function suspendDelinquentSubscriptions(): int
    {
        $suspendDays = (int) config('billing.grace_period.suspend_days', 7);
        $cutoff = now()->subDays($suspendDays);
        $count = 0;

        $subscriptions = Subscription::query()
            ->where('status', 'past_due')
            ->with('tenant')
            ->get();

        foreach ($subscriptions as $subscription) {
            $start = $this->overdueStart($subscription);

            if (! $start || $start->greaterThan($cutoff)) {
                continue;
            }

            $reason = static::SUSPENSION_PREFIX.'subscription_'.$subscription->id;

            $subscription->suspend($reason);

            $tenant = $subscription->tenant;
            if ($tenant && $this->canSuspendTenant($tenant)) {
                try {
                    $this->tenants->suspend($tenant, $reason);
                } catch (\Throwable $e) {
                    Log::warning('Failed to suspend tenant for non-payment', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->notifySuspended($subscription);

            Log::warning('Subscription suspended for non-payment', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'overdue_since' => $start->toISOString(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Expire subscriptions whose oldest overdue invoice exceeds
     * `grace_period.cancel_days`. The tenant remains suspended (no data loss).
     */
    public function cancelDelinquentSubscriptions(): int
    {
        $cancelDays = (int) config('billing.grace_period.cancel_days', 30);
        $cutoff = now()->subDays($cancelDays);
        $count = 0;

        $subscriptions = Subscription::query()
            ->whereIn('status', ['past_due', 'suspended'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $start = $this->overdueStart($subscription);

            if (! $start || $start->greaterThan($cutoff)) {
                continue;
            }

            $subscription->update(['auto_renew' => false]);
            $subscription->expire();

            Log::warning('Subscription expired after extended non-payment', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Send the payment reminder email (and database notification) for a single
     * subscription. Used by the reminder job and the super-admin "send payment
     * email" action.
     *
     * @param  bool  $finalNotice  When true, warn that access will be suspended.
     */
    public function sendPaymentReminder(Subscription $subscription, bool $finalNotice = false): bool
    {
        $owner = $this->resolveOwner($subscription);

        if (! $owner) {
            Log::warning('No owner found for payment reminder', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);

            return false;
        }

        $invoice = $this->oldestOverdueInvoice($subscription);
        $daysPast = $this->overdueStart($subscription)
            ? (int) $this->overdueStart($subscription)->diffInDays(now())
            : null;

        $owner->notify(new PaymentReminderNotification(
            $subscription,
            $invoice,
            $daysPast,
            $finalNotice,
        ));

        return true;
    }

    /**
     * Restore access after a payment resolves the delinquency. Only unsuspends
     * subscriptions/tenants that are still in the past_due/suspended state and
     * have no remaining overdue invoices.
     */
    public function reactivateForRecoveredPayment(Subscription $subscription): bool
    {
        $subscription->refresh();

        $tenant = $subscription->tenant;

        // Still owes money (e.g. a partial payment) — keep the account in dunning.
        if ($this->oldestOverdueInvoice($subscription)) {
            return false;
        }

        $changed = false;

        if (in_array($subscription->status, ['past_due', 'suspended'], true)) {
            $subscription->unsuspend();
            $changed = true;
        }

        if ($tenant && $this->wasSuspendedForBilling($tenant)) {
            try {
                $this->tenants->activate($tenant);
                $changed = true;
            } catch (\Throwable $e) {
                Log::warning('Failed to reactivate tenant after payment', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($changed) {
            Log::info('Subscription access recovered after payment', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);
        }

        return $changed;
    }

    /**
     * The timestamp the subscription first fell behind: the oldest overdue open
     * invoice, else the recorded past_due marker.
     */
    public function overdueStart(Subscription $subscription): ?Carbon
    {
        $invoiceDue = $this->oldestOverdueInvoice($subscription)?->due_at;

        if ($invoiceDue) {
            return Carbon::parse($invoiceDue);
        }

        if ($subscription->past_due_at) {
            return Carbon::parse($subscription->past_due_at);
        }

        if (in_array($subscription->status, ['past_due', 'suspended'], true)) {
            return Carbon::parse($subscription->updated_at ?? $subscription->ends_at ?? now());
        }

        return null;
    }

    protected function oldestOverdueInvoice(Subscription $subscription): ?Invoice
    {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->first();
    }

    protected function notifySuspended(Subscription $subscription): void
    {
        $owner = $this->resolveOwner($subscription);

        if (! $owner) {
            return;
        }

        try {
            $owner->notify(new SubscriptionSuspendedNotification($subscription));
        } catch (\Throwable $e) {
            Log::error('Failed to send subscription suspended notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveOwner(Subscription $subscription): ?User
    {
        try {
            return $subscription->tenant?->getOwner();
        } catch (\Throwable $e) {
            Log::warning('Unable to resolve subscription owner', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function canSuspendTenant(Tenant $tenant): bool
    {
        if ($tenant->status === Tenant::STATUS_ARCHIVED) {
            return false;
        }

        if ($tenant->status === Tenant::STATUS_SUSPENDED) {
            // Already suspended by us — keep it; otherwise leave it alone.
            return $this->wasSuspendedForBilling($tenant);
        }

        return true;
    }

    protected function wasSuspendedForBilling(Tenant $tenant): bool
    {
        return str_starts_with((string) $tenant->suspension_reason, static::SUSPENSION_PREFIX);
    }
}
