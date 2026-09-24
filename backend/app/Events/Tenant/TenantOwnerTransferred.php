<?php

namespace App\Events\Tenant;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantOwnerTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public \App\Models\Tenant $tenant,
        public ?User $newOwner = null,
    ) {
    }
}