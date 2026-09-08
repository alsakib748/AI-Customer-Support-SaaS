<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketActivity extends Model
{
    use SoftDeletes;

    protected $table = 'ticket_activities';

    protected $connection = 'tenant';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function getUserNameAttribute(): string
    {
        if (!$this->user_id) {
            return 'System';
        }
        return 'User #' . $this->user_id;
    }
}