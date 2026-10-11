<?php
namespace App\Http\Controllers\Api\V1\Billing;

use App\Events\Billing\SubscriptionCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Coupon;
use App\Models\Subscription;
use App\Payments\PaymentGatewayManager;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    protected SubscriptionService $service;
    protected PlanService $planService;

    public function __construct(
        SubscriptionService $service,
        PlanService $planService,
        protected PaymentGatewayManager $gateways,
    ) {
        $this->service     = $service;
        $this->planService = $planService;
    }

    /**
     * Get current subscription
     */
    public function current(Request $request)
    {
        try {
            $tenant = app('current_tenant');

            if (! $tenant) {
                if (auth()->user()?->hasRole('super_admin')) {
                    return response()->json([
                        'success' => true,
                        'data'    => null,
                        'message' => 'No tenant context provided.',
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to access billing.',
                ], 403);
            }

            $subscription = $this->service->getActiveSubscription($tenant->id);

            if (! $subscription) {
                return response()->json([
                    'success' => true,
                    'data'    => null,
                    'message' => 'No active subscription.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => new SubscriptionResource($subscription),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get subscription:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new subscription
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id'       => 'required|integer|exists:plans,id',
                'billing_cycle' => 'nullable|in:monthly,yearly',
                'coupon_code'   => 'nullable|string',
            ]);

            $tenant = app('current_tenant');

            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to create a subscription.',
                ], 403);
            }

            // Check if already has subscription
            $existing = $this->service->getActiveSubscription($tenant->id);
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant already has an active subscription.',
                ], 400);
            }

            $plan         = $this->planService->getPlan($validated['plan_id']);
            $billingCycle = $validated['billing_cycle'] ?? 'monthly';

            // Paid plans must never be activated from client-supplied data:
            // they require a verified provider checkout / webhook. Only free
            // plans may be created directly.
            if (! $plan->isFree()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid plans must be purchased through checkout.',
                    'code'    => 'checkout_required',
                ], 422);
            }

            $coupon = null;
            if (! empty($validated['coupon_code'])) {
                $coupon = Coupon::byCode($validated['coupon_code'])->first();
            }

            $subscription = $this->service->createSubscription(
                $tenant,
                $plan,
                $billingCycle,
                $coupon
            );

            event(new SubscriptionCreated($subscription));

            return (new SubscriptionResource($subscription))
                ->additional([
                    'message' => 'Subscription created successfully 🎉',
                ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to create subscription:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upgrade subscription
     */
    public function upgrade(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|integer|exists:plans,id',
            ]);

            $tenant = app('current_tenant');
            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to upgrade subscription.',
                ], 403);
            }

            $subscription = $this->service->getActiveSubscription($tenant->id);

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found.',
                ], 404);
            }

            $newPlan = $this->planService->getPlan($validated['plan_id']);

            // Verify it's an upgrade
            if ($newPlan->price_monthly <= $subscription->plan->price_monthly) {
                return response()->json([
                    'success' => false,
                    'message' => 'New plan must be an upgrade. Use downgrade endpoint for downgrades.',
                ], 400);
            }

            $subscription = $this->service->upgrade($subscription, $newPlan);

            return (new SubscriptionResource($subscription))
                ->additional([
                    'message' => 'Subscription upgraded successfully 🚀',
                ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to upgrade subscription:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upgrade subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Downgrade subscription
     */
    public function downgrade(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|integer|exists:plans,id',
            ]);

            $tenant = app('current_tenant');
            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to downgrade subscription.',
                ], 403);
            }

            $subscription = $this->service->getActiveSubscription($tenant->id);

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found.',
                ], 404);
            }

            $newPlan = $this->planService->getPlan($validated['plan_id']);

            // Verify it's a downgrade
            if ($newPlan->price_monthly >= $subscription->plan->price_monthly) {
                return response()->json([
                    'success' => false,
                    'message' => 'New plan must be a downgrade. Use upgrade endpoint for upgrades.',
                ], 400);
            }

            $subscription = $this->service->downgrade($subscription, $newPlan);

            return (new SubscriptionResource($subscription))
                ->additional([
                    'message' => 'Subscription downgrade scheduled for next billing cycle 📅',
                ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to downgrade subscription:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to downgrade subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    // public function cancel(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'immediately' => 'nullable|boolean',
    //         ]);

    //         $tenant = app('current_tenant');
    //         if (!$tenant) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Tenant context is required to cancel subscription.',
    //             ], 403);
    //         }

    //         $subscription = $this->service->getActiveSubscription($tenant->id);

    //         if (!$subscription) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No active subscription found.',
    //             ], 404);
    //         }

    //         if (!$subscription->canCancel()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Subscription cannot be cancelled.',
    //             ], 400);
    //         }

    //         $immediately = $request->boolean('immediately', false);
    //         $subscription = $this->service->cancel($subscription, $immediately);

    //         $message = $immediately
    //             ? 'Subscription cancelled immediately.'
    //             : 'Subscription will be cancelled at the end of the billing period.';

    //         return (new SubscriptionResource($subscription))
    //             ->additional([
    //                 'message' => $message,
    //             ]);

    //     } catch (\Exception $e) {
    //         Log::error('Failed to cancel subscription:', ['error' => $e->getMessage()]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to cancel subscription: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function cancel(CancelSubscriptionRequest $request)
    {
        try {
            $tenant       = app('current_tenant');
            $subscription = $this->service->getActiveSubscription($tenant->id);

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found.',
                ], 404);
            }

            $subscription = $this->service->cancel(
                $subscription,
                $request->boolean('immediately', false),
                $request->input('reason'),
            );

            return (new SubscriptionResource($subscription))
                ->additional(['message' => 'Subscription cancelled.']);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription.',
            ], 500);
        }
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(Request $request)
    {
        try {
            $tenant = app('current_tenant');
            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to resume subscription.',
                ], 403);
            }

            $subscription = $this->service->getSubscription($tenant->id);

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscription found.',
                ], 404);
            }

            if (! $subscription->is_cancelled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is not cancelled.',
                ], 400);
            }

            $subscription = $this->service->resume($subscription);

            return (new SubscriptionResource($subscription))
                ->additional([
                    'message' => 'Subscription resumed successfully ✅',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to resume subscription:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate coupon
     */
    public function validateCoupon(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string',
            ]);

            $tenant = app('current_tenant');
            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to validate coupon.',
                ], 403);
            }

            $subscription = $this->service->getSubscription($tenant->id);

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscription found.',
                ], 404);
            }

            $result = $this->service->validateCoupon($validated['code'], $subscription);

            return response()->json([
                'success' => $result['valid'],
                'message' => $result['valid']
                    ? 'Coupon is valid!'
                    : ($result['message'] ?? 'Invalid coupon.'),
                'data'    => $result['valid'] ? [
                    'discount' => $result['discount'],
                ] : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to validate coupon:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate coupon.',
            ], 500);
        }
    }

    public function checkout(CheckoutRequest $request, BillingService $billing)
    {

        \Log::info('Checkout request received', [
            'tenant_id' => optional(app('current_tenant'))->id,
            'payload'   => $request->all(),
        ]);

        $tenant   = app('current_tenant');
        $plan     = \App\Models\Plan::on('central')->findOrFail($request->integer('plan_id'));
        $cycle    = $request->input('billing_cycle', 'monthly');
        $provider = $request->input('provider', config('payment.default'));

        try {
            $result = $billing->createCheckout($tenant, $plan, $cycle, $provider);

            $subscription = $this->service->createPendingSubscription(
                $tenant,
                $plan,
                $cycle,
                $provider,
                $result['provider_subscription_id'] ?? null,
                $result['session_id'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'subscription_id' => $subscription->id,
                    'checkout_url'    => $result['checkout_url'],
                    'session_id'      => $result['session_id'] ?? null,
                    'provider'        => $provider,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Checkout failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function providers()
    {
        return response()->json([
            'success' => true,
            'data'    => app(\App\Payments\PaymentGatewayManager::class)->availableProviders(),
        ]);
    }

    /**
     * Verify a completed checkout session (called from the success page).
     */
    public function verify(Request $request, BillingService $billing)
    {
        try {
            $validated = $request->validate([
                'session_id' => ['required', 'string'],
                'provider'   => ['nullable', 'string'],
            ]);

            $tenant   = app('current_tenant');
            $provider = $validated['provider'] ?? config('payment.default');

            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required to verify checkout.',
                ], 403);
            }

            if (! $this->gateways->has($provider)) {
                throw new \InvalidArgumentException("Unsupported provider: {$provider}");
            }

            $session = $this->gateways->driver($provider)->retrieveCheckoutSession($validated['session_id']);

            // Bind the session to this tenant when the provider echoes our metadata.
            $sessionTenantId = $session['metadata']['tenant_id'] ?? null;

            if ($sessionTenantId !== null && (string) $sessionTenantId !== (string) $tenant->id) {
                Log::warning('Checkout session tenant mismatch', [
                    'tenant_id'         => $tenant->id,
                    'session_tenant_id' => $sessionTenantId,
                    'provider'          => $provider,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'This checkout session does not belong to your workspace.',
                ], 403);
            }

            // Providers report "done" differently (Stripe: complete, PayPal: COMPLETED/APPROVED).
            $status = strtolower((string) ($session['status'] ?? ''));

            if (! in_array($status, ['complete', 'completed', 'approved'], true)) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'paid'   => false,
                        'status' => $session['status'] ?? 'unknown',
                    ],
                ]);
            }

            $subscription = Subscription::where('tenant_id', $tenant->id)
                ->where('metadata->checkout_session_id', $validated['session_id'])
                ->first();

            if (! $subscription) {
                // Webhook may have already activated it — return current state.
                $current = $this->service->getActiveSubscription($tenant->id);

                return response()->json([
                    'success' => true,
                    'data'    => $current ? new SubscriptionResource($current) : null,
                ]);
            }

            if ($subscription->status === 'pending') {
                // Never trust the redirect alone: validate the charged amount and
                // currency against the plan before activating. Trials charge nothing.
                $plan           = $subscription->plan;
                $isTrialing     = (int) ($plan?->trial_days ?? 0) > 0;
                $expectedAmount = $plan?->getPriceForCycle($subscription->billing_cycle) ?? 0.0;
                $chargedAmount  = $session['amount_total'] ?? null;
                $chargedCurrency = $session['currency'] !== null ? strtoupper((string) $session['currency']) : null;
                $expectedCurrency = $plan?->currency !== null ? strtoupper((string) $plan->currency) : null;

                if (! $isTrialing && $expectedAmount > 0) {
                    if ($chargedAmount === null
                        || abs((float) $chargedAmount - (float) $expectedAmount) > 0.01) {
                        Log::warning('Checkout amount mismatch', [
                            'tenant_id' => $tenant->id,
                            'provider'  => $provider,
                            'expected'  => $expectedAmount,
                            'charged'   => $chargedAmount,
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'The amount charged does not match the selected plan.',
                            'code'    => 'amount_mismatch',
                        ], 422);
                    }
                }

                if ($expectedCurrency !== null && $chargedCurrency !== null && $chargedCurrency !== $expectedCurrency) {
                    Log::warning('Checkout currency mismatch', [
                        'tenant_id' => $tenant->id,
                        'provider'  => $provider,
                        'expected'  => $expectedCurrency,
                        'charged'   => $chargedCurrency,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'The currency charged does not match the selected plan.',
                        'code'    => 'currency_mismatch',
                    ], 422);
                }

                $subscription = $this->service->completePendingCheckout($subscription, [
                    'provider_subscription_id' => $session['provider_subscription_id'],
                    'provider_customer_id'     => $session['provider_customer_id'],
                    'provider_payment_id'      => $session['provider_payment_id'] ?? null,
                    'provider_invoice_id'      => $session['provider_invoice_id'] ?? null,
                    'source'                   => 'checkout.verify',
                ]);
            }

            if (! $subscription->relationLoaded('plan')) {
                $subscription->load('plan');
            }

            return response()->json([
                'success' => true,
                'data'    => new SubscriptionResource($subscription),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to verify checkout:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify checkout: ' . $e->getMessage(),
            ], 422);
        }
    }

}
