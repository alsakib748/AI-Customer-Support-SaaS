<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\ReconcileSubscriptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-billing-command')]
#[Description('Command description')]
class ReconcileBillingCommand extends Command
{
    protected $signature = 'billing:reconcile 
                            {--sync : Run synchronously}';

    protected $description = 'Reconcile subscriptions with payment gateways';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting subscription reconciliation...');

        if ($this->option('sync')) {
            ReconcileSubscriptions::dispatchSync();
        } else {
            ReconcileSubscriptions::dispatch();
        }

        $this->info('✓ Reconciliation dispatched');

        return self::SUCCESS;
    }

}