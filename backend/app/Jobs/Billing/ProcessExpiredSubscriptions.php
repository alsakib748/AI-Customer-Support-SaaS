<?php

namespace App\Jobs\Billing;

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
        Log::info('Starting expired subscription processing');

        try {
            $count = $subscriptionService->checkExpiredSubscriptions();

            Log::info('Expired subscription processing completed', [
                'expired_count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Expired subscription processing failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}