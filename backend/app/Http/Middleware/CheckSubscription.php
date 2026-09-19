<?php
namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    protected SubscriptionService $service;

    public function __construct(SubscriptionService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure( Request ): ( Response )  $next
     */

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app('current_tenant');

        if (! $tenant) {
            return $next($request);
        }

        $subscription = $this->service->getActiveSubscription($tenant->id);

        // No subscription - allow access to billing only
        if (! $subscription) {
            if ($this->isBillingRoute($request)) {
                return $next($request);
            }

            return response()->json([
                'success'     => false,
                'message'     => 'No active subscription. Please choose a plan.',
                'code'        => 'no_subscription',
                'redirect_to' => '/billing',
            ], 402);
        }

        // Subscription expired
        if ($subscription->is_expired) {
            if ($this->isBillingRoute($request)) {
                return $next($request);
            }

            return response()->json([
                'success'     => false,
                'message'     => 'Your subscription has expired. Please renew.',
                'code'        => 'subscription_expired',
                'redirect_to' => '/billing',
            ], 402);
        }

        // Set subscription in request
        $request->attributes->set('subscription', $subscription);

        return $next($request);
    }

    protected function isBillingRoute(Request $request): bool
    {
        $path = $request->path(); // e.g. "api/v1/billing/usage"

        foreach ([
            'api/v1/subscription',
            'api/v1/invoices',
            'api/v1/payments',
            'api/v1/plans',
            'api/v1/billing', // ← covers /billing/usage
            'api/v1/auth',
            'api/v1/workspace',
            'api/v1/tenants',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

}
