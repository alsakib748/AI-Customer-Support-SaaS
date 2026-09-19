<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\SyncStorageUsage;
use App\Services\Billing\UsageTracker;
use Illuminate\Console\Command;

class SyncStorageUsageCommand extends Command
{
    protected $signature = 'billing:sync-storage
                            {--sync : Run synchronously instead of queueing}
                            {--tenant= : Sync a specific tenant ID only}';

    protected $description = 'Sync storage usage for tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting storage usage sync...');
        $tenantId = $this->option('tenant');

        if ($this->option('sync')) {
            if ($tenantId) {
                $tracker = app(UsageTracker::class);
                $tracker->syncStorageUsage($tenantId);
                $this->info("✓ Storage usage synced for tenant: {$tenantId}");
            } else {
                SyncStorageUsage::dispatchSync();
                $this->info('✓ Storage usage synced synchronously');
            }
        } else {
            SyncStorageUsage::dispatch($tenantId);
            $this->info('✓ Storage usage sync queued');
        }

        return self::SUCCESS;
    }

}
