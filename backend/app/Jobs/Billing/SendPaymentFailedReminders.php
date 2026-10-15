<?php

namespace App\Jobs\Billing;

use App\Models\Subscription;
use App\Services\Billing\BillingDunningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentFailedReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(BillingDunningService $dunning): void
    {
        Log::info('Starting payment failed reminder processing');

        $reminders = (array) config('billing.reminders.payment_failed', [1, 3, 7]);

        foreach ($reminders as $days) {
            $days = (int) $days;
            $this->sendForDays($days, $dunning);
        }

        Log::info('Payment failed reminder processing completed');
    }

    protected function sendForDays(int $days, BillingDunningService $dunning): void
    {
        $cutoffStart = now()->subDays($days + 1)->startOfDay();
        $cutoffEnd = now()->subDays($days)->endOfDay();

        $subscriptions = Subscription::query()
            ->whereIn('status', ['past_due', 'suspended'])
            ->where(function ($q) use ($cutoffStart, $cutoffEnd) {
                $q->whereBetween('past_due_at', [$cutoffStart, $cutoffEnd])
                    ->orWhereBetween('updated_at', [$cutoffStart, $cutoffEnd]);
            })
            ->with(['tenant', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $dunning->sendPaymentReminder($subscription, $days >= 7);
                Log::info('Payment reminder sent', [
                    'subscription_id' => $subscription->id,
                    'days_past' => $days,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send payment reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
