<?php
namespace App\Jobs\Billing;

use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries   = 1;

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
    public function handle(SubscriptionService $service): void
    {
        $graceDays = (int) config('billing.grace_period.past_due_days', 3);
        $cutoff    = now()->subDays($graceDays);

        // Expire "active" subscriptions whose period ended without renewal
        $expired = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->where('auto_renew', false)
            ->get();

        foreach ($expired as $sub) {
            $sub->expire();
            Log::info('Subscription expired (period ended)', ['subscription_id' => $sub->id]);
        }

        // Expire "past_due" subscriptions past the grace period
        $pastDue = Subscription::where('status', 'past_due')
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($pastDue as $sub) {
            $sub->expire();
            Log::info('Subscription expired (grace period ended)', ['subscription_id' => $sub->id]);
        }
    }

}
