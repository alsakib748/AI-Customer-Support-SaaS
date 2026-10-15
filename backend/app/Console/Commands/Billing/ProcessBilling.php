<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\ProcessExpiredSubscriptions;
use App\Jobs\Billing\ProcessSubscriptionDunning;
use App\Jobs\Billing\ProcessSubscriptionRenewals;
use App\Jobs\Billing\ResetMonthlyUsage;
use App\Jobs\Billing\SendPaymentFailedReminders;
use App\Jobs\Billing\SendRenewalReminders;
use App\Jobs\Billing\SendTrialEndingReminder;
use App\Jobs\Billing\SyncStorageUsage;
use Illuminate\Console\Command;

class ProcessBilling extends Command
{
    protected $signature = 'billing:process
                            {--renew : Process renewals}
                            {--expire : Process expirations}
                            {--reminders : Send renewal reminders}
                            {--payment-reminders : Send payment failed reminders}
                            {--suspend : Enforce dunning (past due / suspend / cancel)}
                            {--trial-reminders : Send trial ending reminders}
                            {--reset-usage : Reset monthly usage}
                            {--sync-storage : Sync storage usage}
                            {--all : Run everything}';

    protected $description = 'Process billing operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting billing processing...');

        if ($this->option('all') || $this->hasNoOptions()) {
            $this->runAll();

            return self::SUCCESS;
        }

        if ($this->option('renew')) {
            $this->info('Processing subscription renewals...');
            ProcessSubscriptionRenewals::dispatchSync();
            $this->info('✓ Renewals processed');
        }

        if ($this->option('expire')) {
            $this->info('Processing expired subscriptions...');
            ProcessExpiredSubscriptions::dispatchSync();
            $this->info('✓ Expirations processed');
        }

        if ($this->option('reminders')) {
            $this->info('Sending renewal reminders...');
            SendRenewalReminders::dispatchSync();
            $this->info('✓ Renewal reminders sent');
        }

        if ($this->option('payment-reminders')) {
            $this->info('Sending payment failed reminders...');
            SendPaymentFailedReminders::dispatchSync();
            $this->info('✓ Payment failed reminders sent');
        }

        if ($this->option('suspend')) {
            $this->info('Enforcing dunning policy...');
            ProcessSubscriptionDunning::dispatchSync();
            $this->info('✓ Dunning enforced');
        }

        if ($this->option('trial-reminders')) {
            $this->info('Sending trial ending reminders...');
            SendTrialEndingReminder::dispatchSync();
            $this->info('✓ Trial reminders sent');
        }

        if ($this->option('reset-usage')) {
            $this->info('Resetting monthly usage...');
            ResetMonthlyUsage::dispatchSync();
            $this->info('✓ Usage reset');
        }

        if ($this->option('sync-storage')) {
            $this->info('Syncing storage usage...');
            SyncStorageUsage::dispatchSync();
            $this->info('✓ Storage usage synced');
        }

        $this->info('Billing processing completed!');

        return self::SUCCESS;
    }

    protected function runAll(): void
    {
        $this->info('Running all billing operations...');

        ProcessSubscriptionRenewals::dispatchSync();
        $this->info('✓ Renewals processed');

        ProcessExpiredSubscriptions::dispatchSync();
        $this->info('✓ Expirations processed');

        ProcessSubscriptionDunning::dispatchSync();
        $this->info('✓ Dunning enforced');

        SendRenewalReminders::dispatchSync();
        $this->info('✓ Renewal reminders sent');

        SendPaymentFailedReminders::dispatchSync();
        $this->info('✓ Payment failed reminders sent');

        SendTrialEndingReminder::dispatchSync();
        $this->info('✓ Trial reminders sent');

        ResetMonthlyUsage::dispatchSync();
        $this->info('✓ Usage reset');

        SyncStorageUsage::dispatchSync();
        $this->info('✓ Storage usage synced');

        $this->info('All billing operations completed!');
    }

    protected function hasNoOptions(): bool
    {
        return ! $this->option('renew')
            && ! $this->option('expire')
            && ! $this->option('reminders')
            && ! $this->option('payment-reminders')
            && ! $this->option('suspend')
            && ! $this->option('trial-reminders')
            && ! $this->option('reset-usage')
            && ! $this->option('sync-storage')
            && ! $this->option('all');
    }
}
