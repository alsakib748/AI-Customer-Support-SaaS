<?php

namespace App\Jobs\Billing;

use App\Services\Billing\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 600;
    public $tries = 1;
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
    public function handle(SubscriptionService $subscriptionService): void
    {
        Log::info('Starting subscription renewal processing');

        try {
            $count = $subscriptionService->checkDueSubscriptions();

            Log::info('Subscription renewal processing completed', [
                'renewed_count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription renewal processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}