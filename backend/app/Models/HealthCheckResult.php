<?php

namespace App\Models;

use App\Health\DTOs\HealthCheckResult as HealthCheckResultDto;
use App\Health\Enums\HealthStatus;
use Illuminate\Database\Eloquent\Model;

class HealthCheckResult extends Model
{
    protected $connection = 'central';

    protected $table = 'health_check_results';

    protected $fillable = [
        'check_key',
        'status',
        'summary',
        'metadata',
        'duration_ms',
        'checked_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'duration_ms'=> 'integer',
        'checked_at' => 'datetime',
    ];

    public function toDto(): HealthCheckResultDto
    {
        return HealthCheckResultDto::make(
            $this->check_key,
            HealthStatus::fromString($this->status),
            $this->summary,
            $this->metadata ?? [],
            $this->checked_at,
        )->withDuration((int) $this->duration_ms);
    }
}