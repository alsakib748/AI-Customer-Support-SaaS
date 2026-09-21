<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCustomer extends Model
{
    protected $connection = 'central';
    protected $table      = 'billing_customers';

    protected $fillable = [
        'tenant_id',
        'provider',
        'provider_customer_id',
        'email',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
