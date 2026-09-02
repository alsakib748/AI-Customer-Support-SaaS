<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'customers';

    protected $connection = 'tenant';

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'avatar',
        'status',
        'metadata',
        'tags',
        'notes',
        'default_language',
        'timezone',
        'last_contacted_at',
        'total_conversations',
        'total_tickets',
        'satisfaction_score',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
        'total_conversations' => 'integer',
        'total_tickets' => 'integer',
        'satisfaction_score' => 'decimal:2',
    ];

    protected $appends = [
        'full_name',
        'display_name',
        'initials',
        'status_label',
        'status_color',
    ];

}