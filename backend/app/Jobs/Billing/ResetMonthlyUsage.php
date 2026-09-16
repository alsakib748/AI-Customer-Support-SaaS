<?php

namespace App\Jobs\Billing;

use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetMonthlyUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting monthly usage reset');

        $count = Subscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->whereDay('next_billing_at', now()->day)
            ->count();

        Subscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->whereDay('next_billing_at', now()->day)
            ->each(function ($subscription) {
                $subscription->resetUsage();
            });

        Log::info('Monthly usage reset completed', [
            'subscriptions_reset' => $count,
        ]);
    }
}