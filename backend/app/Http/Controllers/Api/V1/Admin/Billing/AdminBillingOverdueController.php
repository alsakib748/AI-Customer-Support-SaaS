<?php

namespace App\Http\Controllers\Api\V1\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\BillingDunningService;
use Illuminate\Http\Request;

class AdminBillingOverdueController extends Controller
{
    public function __construct(protected BillingDunningService $dunning) {}

    /**
     * List overdue subscriptions with tenant context for the super-admin panel.
     */
    public function index(Request $request)
    {
        $query = Subscription::query()
            ->with(['tenant', 'plan', 'invoices' => function ($q) {
                $q->where('status', 'open')
                    ->whereNotNull('due_at')
                    ->orderBy('due_at');
            }])
            ->whereIn('status', ['past_due', 'suspended'])
            ->latest();

        $subscriptions = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $subscriptions->map(function (Subscription $sub) {
                $oldestOverdue = $sub->invoices->first();
                $start = $this->dunning->overdueStart($sub);

                return [
                    'id' => $sub->id,
                    'uuid' => $sub->uuid,
                    'tenant_id' => $sub->tenant_id,
                    'tenant_name' => $sub->tenant?->name,
                    'plan_name' => $sub->plan?->name,
                    'status' => $sub->status,
                    'provider' => $sub->provider,
                    'amount_due' => $oldestOverdue?->formatted_total,
                    'currency' => $oldestOverdue?->currency,
                    'due_at' => optional($oldestOverdue?->due_at)->toISOString(),
                    'invoice_number' => $oldestOverdue?->invoice_number,
                    'invoice_id' => $oldestOverdue?->id,
                    'days_past_due' => $start ? (int) $start->diffInDays(now()) : null,
                    'suspended_at' => optional($sub->suspended_at)->toISOString(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * Send the payment reminder / pay email to the subscription owner.
     */
    public function sendEmail(Request $request, int $id)
    {
        $subscription = Subscription::with(['tenant', 'plan'])->findOrFail($id);

        $sent = $this->dunning->sendPaymentReminder($subscription, $request->boolean('final_notice', false));

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'Payment reminder email sent.'
                : 'Unable to send payment reminder email (no owner or no invoice context).',
        ]);
    }
}
