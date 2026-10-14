<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemoRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'full_name',
        'email',
        'company',
        'team_size',
        'interests',
        'message',
        'status',
        'scheduled_at',
        'meeting_link',
        'notes',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
