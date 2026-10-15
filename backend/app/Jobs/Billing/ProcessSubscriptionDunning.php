<?php

namespace App\Jobs\Billing;

use App\Services\Billing\BillingDunningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionDunning implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(BillingDunningService $dunning): void
    {
        Log::info('Starting subscription dunning pass');
        $summary = $dunning->enforce();
        Log::info('Subscription dunning pass completed', $summary);
    }
}
