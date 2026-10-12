<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class QuotaReservation extends Model
{
    protected $table = 'quota_reservations';

    protected $connection = 'tenant';

    protected $fillable = [
        'feature',
        'period',
        'reserved_count',
    ];

    protected $casts = [
        'reserved_count' => 'integer',
    ];
}
