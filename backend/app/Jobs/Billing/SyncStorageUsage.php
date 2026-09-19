<?php

namespace App\Jobs\Billing;

use App\Models\Tenant;
use App\Services\Billing\UsageTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStorageUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public ?string $tenantId = null)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(UsageTracker $tracker): void
    {
        Log::info('Starting storage usage sync', ['tenant_id' => $this->tenantId]);

        if ($this->tenantId) {
            $tracker->syncStorageUsage($this->tenantId);
            Log::info('Storage usage synced', ['tenant_id' => $this->tenantId]);
            return;
        }

        $count = 0;

        Tenant::query()
            ->whereHas('subscriptions', fn($q) => $q->whereIn('status', ['active', 'trialing']))
            ->chunk(100, function ($tenants) use ($tracker, &$count) {
                foreach ($tenants as $tenant) {
                    try {
                        $tracker->syncStorageUsage($tenant->id);
                        $count++;
                    } catch (\Throwable $e) {
                        Log::error('Failed to sync storage usage', [
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('Storage usage sync completed', ['tenants_synced' => $count]);
    }

}
