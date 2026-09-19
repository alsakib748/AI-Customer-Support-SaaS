<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingAnalyticsService
{
    public function getPlatformMetrics(): array
    {
        $now = now();

        $mrr = $this->calculateMrr();
        $arr = $mrr * 12;

        $active = Subscription::where('status', 'active')->count();
        $trialing = Subscription::where('status', 'trialing')->count();
        $pastDue = Subscription::where('status', 'past_due')->count();
        $cancelled = Subscription::where('status', 'cancelled')->count();
        $expired = Subscription::where('status', 'expired')->count();

        $newThisMonth = Subscription::whereBetween('created_at', [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ])->count();

        $churnedThisMonth = Subscription::where('status', 'cancelled')
            ->whereBetween('cancelled_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ])
            ->count();

        $churnRate = $active > 0
            ? round(($churnedThisMonth / max($active, 1)) * 100, 2)
            : 0.0;

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'active_subscriptions' => $active,
            'trialing_tenants' => $trialing,
            'past_due' => $pastDue,
            'cancelled' => $cancelled,
            'expired' => $expired,
            'new_subscriptions' => $newThisMonth,
            'churned_subscriptions' => $churnedThisMonth,
            'churn_rate' => $churnRate,
            'plan_distribution' => $this->getPlanDistribution(),
            'revenue_last_30_days' => $this->revenueLast30Days(),
            'payment_failures_last_30_days' => $this->paymentFailuresLast30Days(),
        ];
    }

    public function calculateMrr(): float
    {
        $rows = Subscription::with('plan')
            ->whereIn('status', ['active', 'past_due'])
            ->get();

        $mrr = 0.0;

        foreach ($rows as $subscription) {
            if (!$subscription->plan) {
                continue;
            }

            $monthly = (float) $subscription->plan->price_monthly;
            $yearly = (float) $subscription->plan->price_yearly;

            if ($subscription->billing_cycle === 'yearly') {
                $mrr += $yearly > 0 ? $yearly / 12 : 0;
            } else {
                $mrr += $monthly;
            }

            // Include subscription items
            foreach ($subscription->items as $item) {
                $mrr += (float) $item->total_price;
            }
        }

        return $mrr;
    }

    public function getPlanDistribution(): array
    {
        return Subscription::query()
            ->select('plan_id', DB::raw('count(*) as total'))
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->groupBy('plan_id')
            ->with('plan:id,name,slug')
            ->get()
            ->map(fn($row) => [
                'plan_id' => $row->plan_id,
                'plan_name' => $row->plan?->name ?? 'Unknown',
                'count' => (int) $row->total,
            ])
            ->toArray();
    }

    public function revenueLast30Days(): float
    {
        return (float) Payment::where('status', 'completed')
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');
    }

    public function paymentFailuresLast30Days(): int
    {
        return Payment::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    public function getTenantBillingSummary(string $tenantId): array
    {
        $subscription = Subscription::with(['plan', 'items'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        return [
            'subscription' => $subscription,
            'invoices' => Invoice::where('tenant_id', $tenantId)->latest()->limit(10)->get(),
            'payments' => Payment::where('tenant_id', $tenantId)->latest()->limit(10)->get(),
            'total_paid' => (float) Payment::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->sum('amount'),
            'outstanding' => (float) Invoice::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->sum('total'),
        ];
    }
}
