<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthMetricSample extends Model
{
    protected $connection = 'central';

    protected $table = 'health_metric_samples';

    protected $fillable = [
        'metric_key',
        'source',
        'value',
        'unit',
        'recorded_at',
    ];

    protected $casts = [
        'value'       => 'decimal:4',
        'recorded_at' => 'datetime',
    ];
}