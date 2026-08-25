<?php
// app/Models/Domain.php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends BaseDomain
{
    use SoftDeletes;

    protected $fillable = [
        'domain',
        'tenant_id',
        'is_primary',
        'verified_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'json',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }
}
