<?php
// app/Services/Billing/SubscriptionService.php

namespace App\Services\Billing;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Get subscription for tenant
     */
    public function getSubscription(string $tenantId): ?Subscription
    {
        return Subscription::forTenant($tenantId)
            ->with(['plan'])
            ->latest()
            ->first();
    }

    /**
     * Get active subscription for tenant
     */
    public function getActiveSubscription(string $tenantId): ?Subscription
    {
        return Subscription::forTenant($tenantId)
            ->active()
            ->with(['plan'])
            ->latest()
            ->first();
    }

    /**
     * Create a new subscription
     */
    public function createSubscription(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle = 'monthly',
        ?Coupon $coupon = null
    ): Subscription {
        return DB::transaction(function () use ($tenant, $plan, $billingCycle, $coupon) {
            $now         = now();
            $isTrialing  = $plan->trial_days > 0;
            $trialEndsAt = $isTrialing ? $now->copy()->addDays($plan->trial_days) : null;
            $periodEnd   = $billingCycle === 'yearly'
                ? $now->copy()->addYear()
                : $now->copy()->addMonth();

            // Create subscription
            $subscription = Subscription::create([
                'tenant_id'           => $tenant->id,
                'plan_id'             => $plan->id,
                'status'              => $isTrialing ? 'trialing' : 'active',
                'billing_cycle'       => $billingCycle,
                'trial_starts_at'     => $isTrialing ? $now : null,
                'trial_ends_at'       => $trialEndsAt,
                'starts_at'           => $now,
                'ends_at'             => $isTrialing ? $trialEndsAt : $periodEnd,
                'auto_renew'          => true,
                'next_billing_at'     => $isTrialing ? $trialEndsAt : $periodEnd,
                'ai_limit'            => $plan->getLimit('ai_messages', 1000),
                'agents_limit'        => $plan->getLimit('agents', 5),
                'documents_limit'     => $plan->getLimit('documents', 100),
                'storage_limit'       => $plan->getLimit('storage_bytes', 1073741824),
                'conversations_limit' => $plan->getLimit('conversations', 500),
            ]);

            // Apply coupon if provided
            if ($coupon && $coupon->isValid()) {
                $this->applyCoupon($subscription, $coupon);
            }

            // Create initial invoice if not trialing and not free
            if (! $isTrialing && ! $plan->isFree()) {
                $this->invoiceService->createSubscriptionInvoice($subscription);
            }

            Log::info('Subscription created', [
                'subscription_id' => $subscription->id,
                'tenant_id'       => $tenant->id,
                'plan_id'         => $plan->id,
                'billing_cycle'   => $billingCycle,
            ]);

            return $subscription;
        });
    }

    /**
     * Upgrade subscription
     */
    public function upgrade(Subscription $subscription, Plan $newPlan): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan) {
            $oldPlan = $subscription->plan;

            // Calculate prorated amount
            $prorationAmount = $this->calculateProration($subscription, $newPlan);

            // Switch plan
            $subscription->switchPlan($newPlan);

            // Create proration invoice if needed
            if ($prorationAmount > 0) {
                $this->invoiceService->createProrationInvoice($subscription, $prorationAmount);
            }

            Log::info('Subscription upgraded', [
                'subscription_id'  => $subscription->id,
                'old_plan_id'      => $oldPlan?->id,
                'new_plan_id'      => $newPlan->id,
                'proration_amount' => $prorationAmount,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Downgrade subscription
     */
    public function downgrade(Subscription $subscription, Plan $newPlan): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan) {
            $oldPlan = $subscription->plan;

            // Switch plan (takes effect at next billing cycle)
            $subscription->update([
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'pending_plan_id'        => $newPlan->id,
                    'pending_plan_name'      => $newPlan->name,
                    'downgrade_scheduled_at' => now()->toISOString(),
                ]),
            ]);

            Log::info('Subscription downgrade scheduled', [
                'subscription_id' => $subscription->id,
                'old_plan_id'     => $oldPlan?->id,
                'new_plan_id'     => $newPlan->id,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Cancel subscription
     */
    // public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    // {
    //     $subscription->cancel($immediately);

    //     Log::info('Subscription cancelled', [
    //         'subscription_id' => $subscription->id,
    //         'immediately' => $immediately,
    //     ]);

    //     return $subscription->fresh();
    // }

    public function cancel(
        Subscription $subscription,
        bool $immediately = false,
        ?string $reason = null,
    ): Subscription {
        return DB::transaction(function () use ($subscription, $immediately, $reason) {
            $subscription->cancel($immediately);

            $subscription->update([
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancellation_reason' => $reason,
                    'cancelled_by'        => auth()->id(),
                    'cancelled_at'        => now()->toISOString(),
                    'immediately'         => $immediately,
                ]),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(Subscription $subscription): Subscription
    {
        if (! $subscription->canCancel()) {
            throw new \Exception('Subscription cannot be resumed.');
        }

        $subscription->resume();

        Log::info('Subscription resumed', [
            'subscription_id' => $subscription->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * Pause subscription
     */
    public function pause(Subscription $subscription): Subscription
    {
        $subscription->pause();

        Log::info('Subscription paused', [
            'subscription_id' => $subscription->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * Renew subscription
     */
    public function renew(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            // Check for pending downgrade
            $pendingPlanId = data_get($subscription->metadata, 'pending_plan_id');

            if ($pendingPlanId) {
                $pendingPlan = Plan::find($pendingPlanId);
                if ($pendingPlan) {
                    $subscription->switchPlan($pendingPlan);
                    $subscription->update([
                        'metadata' => array_diff_key(
                            $subscription->metadata ?? [],
                            ['pending_plan_id' => '', 'downgrade_scheduled_at' => '']
                        ),
                    ]);
                }
            }

            // Renew
            $subscription->renew();
            $subscription->resetUsage();

            // Create renewal invoice
            if (! $subscription->plan->isFree()) {
                $this->invoiceService->createRenewalInvoice($subscription);
            }

            Log::info('Subscription renewed', [
                'subscription_id' => $subscription->id,
                'new_period_end'  => $subscription->ends_at,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Apply coupon to subscription
     */
    public function applyCoupon(Subscription $subscription, Coupon $coupon): CouponRedemption
    {
        if (! $coupon->isValid()) {
            throw new \Exception('Coupon is not valid.');
        }

        if (! $coupon->canBeRedeemedBy($subscription->tenant_id)) {
            throw new \Exception('Coupon has already been redeemed.');
        }

        if ($subscription->plan && ! $coupon->isApplicableToPlan($subscription->plan)) {
            throw new \Exception('Coupon is not applicable to this plan.');
        }

        return $coupon->redeem(
            $subscription->tenant_id,
            $subscription->id
        );
    }

    /**
     * Validate coupon for subscription
     */
    public function validateCoupon(string $code, Subscription $subscription): array
    {
        $coupon = Coupon::byCode($code)->first();

        if (! $coupon) {
            return [
                'valid'   => false,
                'message' => 'Coupon not found.',
            ];
        }

        if (! $coupon->isValid()) {
            return [
                'valid'   => false,
                'message' => 'Coupon is expired or no longer valid.',
            ];
        }

        if (! $coupon->canBeRedeemedBy($subscription->tenant_id)) {
            return [
                'valid'   => false,
                'message' => 'You have already used this coupon.',
            ];
        }

        if ($subscription->plan && ! $coupon->isApplicableToPlan($subscription->plan)) {
            return [
                'valid'   => false,
                'message' => 'Coupon is not applicable to your current plan.',
            ];
        }

        return [
            'valid'    => true,
            'coupon'   => $coupon,
            'discount' => $coupon->formatted_value,
        ];
    }

    /**
     * Check and update expired subscriptions
     */
    public function checkExpiredSubscriptions(): int
    {
        $expired = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->where('auto_renew', false)
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            $subscription->expire();
            $count++;

            Log::info('Subscription expired', [
                'subscription_id' => $subscription->id,
                'tenant_id'       => $subscription->tenant_id,
            ]);
        }

        return $count;
    }

    /**
     * Check subscriptions due for renewal
     */
    public function checkDueSubscriptions(): int
    {
        $due = Subscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('next_billing_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($due as $subscription) {
            try {
                $this->renew($subscription);
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to renew subscription', [
                    'subscription_id' => $subscription->id,
                    'error'           => $e->getMessage(),
                ]);

                $subscription->markAsPastDue();
            }
        }

        return $count;
    }

    /**
     * Calculate proration amount
     */
    protected function calculateProration(Subscription $subscription, Plan $newPlan): float
    {
        $currentPlan = $subscription->plan;

        if (! $currentPlan || ! $subscription->ends_at) {
            return 0;
        }

        $now         = now();
        $periodStart = $subscription->starts_at ?? $now;
        $periodEnd   = $subscription->ends_at;

        // Calculate remaining days
        $totalDays     = $periodStart->diffInDays($periodEnd);
        $remainingDays = $now->diffInDays($periodEnd);

        if ($totalDays <= 0) {
            return 0;
        }

        $remainingRatio = $remainingDays / $totalDays;

        // Current plan value for remaining period
        $currentPlanPrice = $currentPlan->getPriceForCycle($subscription->billing_cycle);
        $currentValue     = $currentPlanPrice * $remainingRatio;

        // New plan value for remaining period
        $newPlanPrice = $newPlan->getPriceForCycle($subscription->billing_cycle);
        $newValue     = $newPlanPrice * $remainingRatio;

        // Proration = new plan value - current plan value (credit)
        return max(0, $newValue - $currentValue);
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): array
    {
        $query = Subscription::query();

        return [
            'total'     => $query->count(),
            'active'    => (clone $query)->where('status', 'active')->count(),
            'trialing'  => (clone $query)->where('status', 'trialing')->count(),
            'past_due'  => (clone $query)->where('status', 'past_due')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'expired'   => (clone $query)->where('status', 'expired')->count(),
            'by_plan'   => (clone $query)
                ->selectRaw('plan_id, count(*) as count')
                ->groupBy('plan_id')
                ->with('plan')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->plan?->name ?? 'Unknown' => $item->count];
                })
                ->toArray(),
            'by_cycle'  => (clone $query)
                ->selectRaw('billing_cycle, count(*) as count')
                ->groupBy('billing_cycle')
                ->pluck('count', 'billing_cycle')
                ->toArray(),
        ];
    }

    /**
     * Add an item to subscription
     */
    public function addItem(Subscription $subscription, array $data): SubscriptionItem
    {
        $quantity  = $data['quantity'] ?? 1;
        $unitPrice = $data['unit_price'] ?? 0;

        $item = $subscription->items()->create([
            'type'        => $data['type'],
            'name'        => $data['name'],
            'slug'        => \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'metadata'    => $data['metadata'] ?? null,
        ]);

        Log::info('Subscription item added', [
            'subscription_id' => $subscription->id,
            'item_id'         => $item->id,
            'type'            => $data['type'],
        ]);

        return $item;
    }

    /**
     * Update a subscription item
     */
    public function updateItem(SubscriptionItem $item, array $data): SubscriptionItem
    {
        $item->update($data);

        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $item->calculateTotal();
        }

        Log::info('Subscription item updated', [
            'item_id' => $item->id,
        ]);

        return $item->fresh();
    }

    /**
     * Remove a subscription item
     */
    public function removeItem(SubscriptionItem $item): bool
    {
        $item->delete();

        Log::info('Subscription item removed', [
            'item_id' => $item->id,
        ]);

        return true;
    }

    /**
     * Add extra seats to subscription
     */
    public function addSeats(Subscription $subscription, int $quantity, float $pricePerSeat = 10): SubscriptionItem
    {
        // Check if seat item exists
        $existingSeat = $subscription->seats()->first();

        if ($existingSeat) {
            return $this->updateItem($existingSeat, [
                'quantity' => $existingSeat->quantity + $quantity,
            ]);
        }

        return $this->addItem($subscription, [
            'type'       => 'seat',
            'name'       => 'Additional Agent Seat',
            'quantity'   => $quantity,
            'unit_price' => $pricePerSeat,
        ]);
    }

}
