<?php
namespace App\Http\Controllers\Api\V1\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePlanRequest;
use App\Http\Requests\Billing\UpdatePlanRequest;
use App\Http\Resources\Billing\InvoiceResource;
use App\Http\Resources\Billing\PaymentResource;
use App\Http\Resources\Billing\PlanResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingAnalyticsService;
use App\Services\Billing\PlanService;
use Illuminate\Http\Request;

class AdminBillingController extends Controller
{

    public function __construct(
        protected PlanService $plans,
        protected BillingAnalyticsService $analytics,
    ) {
    }

    // -------- PLANS --------

    public function plans(Request $request)
    {
        $query = Plan::query()->orderBy('sort_order')->orderBy('price_monthly');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return PlanResource::collection($query->paginate(50));
    }

    public function storePlan(StorePlanRequest $request)
    {
        $plan = $this->plans->createPlan($request->validated());

        return (new PlanResource($plan))
            ->additional(['message' => 'Plan created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function updatePlan(UpdatePlanRequest $request, Plan $plan)
    {
        $plan = $this->plans->updatePlan($plan, $request->validated());

        return (new PlanResource($plan))
            ->additional(['message' => 'Plan updated successfully.']);
    }

    public function destroyPlan(Plan $plan)
    {
        $this->plans->deletePlan($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully.',
        ]);
    }

    // -------- SUBSCRIPTIONS --------

    public function subscriptions(Request $request)
    {
        $query = Subscription::with(['plan', 'tenant'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn($q, $p) => $q->where('plan_id', $p))
            ->when($request->tenant_id, fn($q, $t) => $q->where('tenant_id', $t))
            ->latest();

        return SubscriptionResource::collection($query->paginate(50));
    }

    public function cancelSubscription(Request $request, int $id)
    {
        $subscription = Subscription::findOrFail($id);

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew'   => false,
            'metadata'     => array_merge($subscription->metadata ?? [], [
                'cancelled_by_admin' => auth()->id(),
                'cancelled_at'       => now()->toISOString(),
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled.',
            'data'    => new SubscriptionResource($subscription->fresh()),
        ]);
    }

    public function extendSubscription(Request $request, int $id)
    {
        $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $subscription = Subscription::findOrFail($id);
        $newEnd       = ($subscription->ends_at ?? now())->addDays($request->days);

        $subscription->update([
            'ends_at'         => $newEnd,
            'next_billing_at' => $newEnd,
            'metadata'        => array_merge($subscription->metadata ?? [], [
                'extended_by_admin' => auth()->id(),
                'extended_at'       => now()->toISOString(),
                'extended_days'     => $request->days,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Subscription extended by {$request->days} days.",
            'data' => new SubscriptionResource($subscription->fresh()),
        ]);
    }

    // -------- INVOICES --------

    public function invoices(Request $request)
    {
        $query = Invoice::with(['tenant', 'subscription'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->tenant_id, fn($q, $t) => $q->where('tenant_id', $t))
            ->latest();

        return InvoiceResource::collection($query->paginate(50));
    }

    // -------- PAYMENTS --------

    public function payments(Request $request)
    {
        $query = Payment::with(['tenant', 'invoice'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->provider, fn($q, $p) => $q->where('provider', $p))
            ->when($request->tenant_id, fn($q, $t) => $q->where('tenant_id', $t))
            ->latest();

        return PaymentResource::collection($query->paginate(50));
    }

    // -------- ANALYTICS --------

    public function analytics()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->analytics->getPlatformMetrics(),
        ]);
    }

    public function tenantSummary(string $tenantId)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->analytics->getTenantBillingSummary($tenantId),
        ]);
    }

}
