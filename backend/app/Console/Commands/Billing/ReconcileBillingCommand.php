<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\ReconcileSubscriptions;
use Illuminate\Console\Command;

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
            $this->info('✓ Reconciliation completed');
        } else {
            ReconcileSubscriptions::dispatch();
            $this->info('✓ Reconciliation dispatched');
        }

        $this->info('✓ Reconciliation dispatched');


        return self::SUCCESS;
    }

}
