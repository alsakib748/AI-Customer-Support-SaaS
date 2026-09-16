<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\SyncStorageUsage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-storage-usage-command')]
#[Description('Command description')]
class SyncStorageUsageCommand extends Command
{
    protected $signature = 'billing:sync-storage 
                            {--sync : Run synchronously instead of queueing}';

    protected $description = 'Sync storage usage for all tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting storage usage sync...');

        $tenantId = $this->option('tenant');

        if ($this->option('sync')) {
            if ($tenantId) {
                $tracker = app(\App\Services\Billing\UsageTracker::class);
                $tracker->syncStorageUsage($tenantId);
                $this->info("✓ Storage usage synced for tenant: {$tenantId}");
            } else {
                SyncStorageUsage::dispatchSync();
                $this->info('✓ Storage usage synced synchronously');
            }
        } else {
            SyncStorageUsage::dispatch();
            $this->info('✓ Storage usage sync queued');
        }

        return self::SUCCESS;
    }

}