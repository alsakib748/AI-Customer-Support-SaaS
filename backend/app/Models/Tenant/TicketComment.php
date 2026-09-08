<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketComment extends Model
{
    use SoftDeletes;

    protected $table = 'ticket_comments';

    protected $connection = 'tenant';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $appends = [
        'type_label',
        'user_name',
        'is_internal',
        'is_reply',
        'is_system',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'reply' => 'Reply',
            'internal_note' => 'Internal Note',
            'system' => 'System',
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    public function getUserNameAttribute(): string
    {
        if (!$this->user_id) {
            return $this->type === 'system' ? 'System' : 'Unknown';
        }
        // Will be resolved from central DB
        return 'User #' . $this->user_id;
    }

    public function getIsInternalAttribute(): bool
    {
        return $this->type === 'internal_note';
    }

    public function getIsReplyAttribute(): bool
    {
        return $this->type === 'reply';
    }

    public function getIsSystemAttribute(): bool
    {
        return $this->type === 'system';
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeInternal($query)
    {
        return $query->where('type', 'internal_note');
    }

    public function scopeReplies($query)
    {
        return $query->where('type', 'reply');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }
}
