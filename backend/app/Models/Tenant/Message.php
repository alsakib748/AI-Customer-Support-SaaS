<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use SoftDeletes;

    protected $table = 'messages';

    protected $connection = 'tenant';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'message_type',
        'content',
        'is_internal',
        'metadata',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'sender_name',
        'sender_avatar',
        'is_customer',
        'is_agent',
        'is_ai',
        'is_system',
        'is_internal_note',
        'is_text_message',
        'time_ago',
        'formatted_date',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getSenderNameAttribute(): string
    {
        if ($this->sender_type === 'customer') {
            $customer = Customer::find($this->sender_id);
            return $customer?->full_name ?? 'Unknown Customer';
        }

        if ($this->sender_type === 'agent') {
            // Will be resolved from central DB
            return 'Agent #' . $this->sender_id;
        }

        if ($this->sender_type === 'ai') {
            return 'AI Assistant';
        }

        if ($this->sender_type === 'system') {
            return 'System';
        }

        return 'Unknown';
    }

    public function getSenderAvatarAttribute(): ?string
    {
        if ($this->sender_type === 'customer') {
            $customer = Customer::find($this->sender_id);
            return $customer?->avatar_url;
        }

        return null;
    }

    public function getIsCustomerAttribute(): bool
    {
        return $this->sender_type === 'customer';
    }

    public function getIsAgentAttribute(): bool
    {
        return $this->sender_type === 'agent';
    }

    public function getIsAiAttribute(): bool
    {
        return $this->sender_type === 'ai';
    }

    public function getIsSystemAttribute(): bool
    {
        return $this->sender_type === 'system';
    }

    public function getIsInternalNoteAttribute(): bool
    {
        return $this->is_internal && $this->message_type === 'internal_note';
    }

    public function getIsTextMessageAttribute(): bool
    {
        return $this->message_type === 'text' && !$this->is_internal;
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : '—';
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y, h:i A') : '—';
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeCustomerMessages($query)
    {
        return $query->where('sender_type', 'customer');
    }

    public function scopeAgentMessages($query)
    {
        return $query->where('sender_type', 'agent');
    }

    public function scopeAIMessages($query)
    {
        return $query->where('sender_type', 'ai');
    }

    public function scopeSystemMessages($query)
    {
        return $query->where('sender_type', 'system');
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeTextMessages($query)
    {
        return $query->where('message_type', 'text');
    }

    public function scopeInternalNotes($query)
    {
        return $query->where('message_type', 'internal_note')
            ->where('is_internal', true);
    }

    public function scopeSystemEvents($query)
    {
        return $query->where('message_type', 'system_event')
            ->where('sender_type', 'system');
    }

    // ============================================
    // METHODS
    // ============================================

    public function isFromCustomer(): bool
    {
        return $this->sender_type === 'customer';
    }

    public function isFromAgent(): bool
    {
        return $this->sender_type === 'agent';
    }

    public function isFromAI(): bool
    {
        return $this->sender_type === 'ai';
    }

    public function isFromSystem(): bool
    {
        return $this->sender_type === 'system';
    }

    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    public function isPublic(): bool
    {
        return !$this->is_internal;
    }

    public function isText(): bool
    {
        return $this->message_type === 'text';
    }

    public function isInternalNote(): bool
    {
        return $this->message_type === 'internal_note' && $this->is_internal;
    }

    public function isSystemEvent(): bool
    {
        return $this->message_type === 'system_event' && $this->sender_type === 'system';
    }
}