<?php

namespace App\Events\Tenant;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantProvisioningFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public \App\Models\Tenant $tenant,
        public ?string $reason = null,
    ) {
    }
}