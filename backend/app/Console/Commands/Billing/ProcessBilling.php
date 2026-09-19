<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\ProcessExpiredSubscriptions;
use App\Jobs\Billing\ProcessSubscriptionRenewals;
use App\Jobs\Billing\ResetMonthlyUsage;
use App\Jobs\Billing\SendRenewalReminders;
use App\Jobs\Billing\SendTrialEndingReminder;
use Illuminate\Console\Command;

class ProcessBilling extends Command
{
    protected $signature = 'billing:process
                            {--renew : Process renewals}
                            {--expire : Process expirations}
                            {--reminders : Send renewal reminders}
                            {--trial-reminders : Send trial ending reminders}
                            {--reset-usage : Reset monthly usage}
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

        SendRenewalReminders::dispatchSync();
        $this->info('✓ Renewal reminders sent');

        SendTrialEndingReminder::dispatchSync();
        $this->info('✓ Trial reminders sent');

        $this->info('All billing operations completed!');
    }

    protected function hasNoOptions(): bool
    {
        return !$this->option('renew')
            && !$this->option('expire')
            && !$this->option('reminders')
            && !$this->option('trial-reminders')
            && !$this->option('reset-usage')
            && !$this->option('all');
    }

}
