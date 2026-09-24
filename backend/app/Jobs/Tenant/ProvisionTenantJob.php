<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant;
use App\Services\Tenant\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public Tenant $tenant,
        public array $data = [],
    ) {
    }

    public function handle(TenantProvisioningService $provisioning): void
    {
        $provisioning->provision($this->tenant, $this->data);
    }

    public function failed(\Throwable $e): void
    {
        $this->tenant->update([
            'status'             => Tenant::STATUS_PROVISIONING_FAILED,
            'provisioning_error' => $e->getMessage(),
        ]);
    }
}