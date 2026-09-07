<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WidgetSession extends Model
{
    use SoftDeletes;

    protected $table = 'widget_sessions';

    protected $connection = 'tenant';

    protected $fillable = [
        'widget_id',
        'visitor_token',
        'session_token',
        'customer_id',
        'current_conversation_id',
        'metadata',
        'last_seen_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected $appends = [
        'is_active',
        'has_customer',
        'has_conversation',
    ];

    // ============================================
    // BOOT
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->visitor_token)) {
                $model->visitor_token = 'v_' . Str::random(32);
            }
            if (empty($model->session_token)) {
                $model->session_token = 'ws_' . Str::random(40);
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'current_conversation_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsActiveAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 30;
    }

    public function getHasCustomerAttribute(): bool
    {
        return !is_null($this->customer_id);
    }

    public function getHasConversationAttribute(): bool
    {
        return !is_null($this->current_conversation_id);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(30));
    }

    public function scopeBySessionToken($query, $token)
    {
        return $query->where('session_token', $token);
    }

    public function scopeByVisitorToken($query, $token)
    {
        return $query->where('visitor_token', $token);
    }

    // ============================================
    // METHODS
    // ============================================

    public function hasCustomer(): bool
    {
        return !is_null($this->customer_id);
    }

    public function hasConversation(): bool
    {
        return !is_null($this->current_conversation_id);
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    public function associateCustomer(Customer $customer): void
    {
        $this->update(['customer_id' => $customer->id]);
    }

    public function associateConversation(Conversation $conversation): void
    {
        $this->update(['current_conversation_id' => $conversation->id]);
    }
}
