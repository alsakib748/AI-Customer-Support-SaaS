<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $table = 'conversations';

    protected $connection = 'tenant';

    protected $fillable = [
        'customer_id',
        'subject',
        'channel',
        'status',
        'priority',
        'assigned_user_id',
        'last_message_at',
        'started_at',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'priority_label',
        'priority_color',
        'channel_label',
        'time_ago',
    ];

    // todo; ============== RELATIONSHIPS ==============
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }


    //todo; =============== ACCESSORS ================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'open' => 'Open',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'open' => 'info',
            'pending' => 'warning',
            'resolved' => 'success',
            'closed' => 'secondary',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
        return $labels[$this->priority] ?? ucfirst($this->priority);
    }

    public function getPriorityColorAttribute(): string
    {
        $colors = [
            'low' => 'secondary',
            'normal' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
        ];
        return $colors[$this->priority] ?? 'secondary';
    }

    public function getChannelLabelAttribute(): string
    {
        $labels = [
            'web' => 'Website',
            'api' => 'API',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
        ];
        return $labels[$this->channel] ?? ucfirst($this->channel);
    }

    public function getTimeAgoAttribute(): string
    {
        $date = $this->last_message_at ?? $this->created_at;
        return $date ? $date->diffForHumans() : '—';
    }

    public function getAssignedUserNameAttribute(): ?string
    {
        if (!$this->assigned_user_id) {
            return null;
        }

        // Will be resolved from central DB when we implement user resolution
        return null;
    }

    // ============================================
    // todo; ================ SCOPES ================
    // ============================================

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'pending']);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_user_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'ILIKE', "%{$search}%")
                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%");
                });
        });
    }

    // ============================================
    // todo; =============== METHODS  ============
    // ============================================

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'pending']);
    }

    public function canResolve(): bool
    {
        return in_array($this->status, ['open', 'pending']);
    }

    public function canReopen(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function canClose(): bool
    {
        return in_array($this->status, ['open', 'pending', 'resolved']);
    }

    public function canAssign(): bool
    {
        return in_array($this->status, ['open', 'pending']);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function assign(int $userId): void
    {
        $this->update([
            'assigned_user_id' => $userId,
        ]);
    }

    public function unassign(): void
    {
        $this->update([
            'assigned_user_id' => null,
        ]);
    }

    public function updateLastMessage(): void
    {
        $this->update([
            'last_message_at' => now(),
        ]);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest('created_at');
    }

    public function publicMessages(): HasMany
    {
        return $this->hasMany(Message::class)->where('is_internal', false);
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(Message::class)
            ->where('is_internal', true)
            ->where('message_type', 'internal_note');
    }

    public function getMessagesCountAttribute(): int
    {
        return $this->messages()->count();
    }

    public function getPublicMessagesCountAttribute(): int
    {
        return $this->messages()->where('is_internal', false)->count();
    }

    public function hasUnreadMessages(): bool
    {
        // Will be implemented with read receipts later
        return false;
    }

}