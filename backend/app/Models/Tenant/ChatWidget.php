<?php

namespace App\Models\Tenant;

use App\Models\Tenant\WidgetSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChatWidget extends Model
{
    use SoftDeletes;

    protected $table = 'chat_widgets';

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'public_key',
        'status',
        'position',
        'header_title',
        'welcome_message',
        'offline_message',
        'primary_color',
        'logo',
        'avatar',
        'show_branding',
        'require_name',
        'require_email',
        'require_phone',
        'allowed_origins',
        'settings',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'settings' => 'array',
        'show_branding' => 'boolean',
        'require_name' => 'boolean',
        'require_email' => 'boolean',
        'require_phone' => 'boolean',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'is_active',
    ];

    // ============================================
    // BOOT
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->public_key)) {
                $model->public_key = 'cw_' . Str::random(32);
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function sessions(): HasMany
    {
        return $this->hasMany(WidgetSession::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'disabled' => 'Disabled',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'active' => 'success',
            'inactive' => 'warning',
            'disabled' => 'danger',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getPositionOptionsAttribute(): array
    {
        return [
            'bottom-right' => 'Bottom Right',
            'bottom-left' => 'Bottom Left',
            'top-right' => 'Top Right',
            'top-left' => 'Top Left',
        ];
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPublicKey($query, $key)
    {
        return $query->where('public_key', $key);
    }

    // ============================================
    // METHODS
    // ============================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function enable(): void
    {
        $this->update(['status' => 'active']);
    }

    public function disable(): void
    {
        $this->update(['status' => 'disabled']);
    }

    public function regenerateKey(): void
    {
        $this->update(['public_key' => 'cw_' . Str::random(32)]);
    }

    public function isOriginAllowed(string $origin): bool
    {
        if (empty($this->allowed_origins)) {
            return true;
        }

        return in_array($origin, $this->allowed_origins);
    }

    public function getInstallationCode(): string
    {
        return sprintf(
            '<script src="%s/widget/chat.js" data-widget-id="%s"></script>',
            config('app.url'),
            $this->public_key
        );
    }

    public function getPositionLabelAttribute(): string
    {
        $positions = [
            'bottom-right' => 'Bottom Right',
            'bottom-left' => 'Bottom Left',
            'top-right' => 'Top Right',
            'top-left' => 'Top Left',
        ];
        return $positions[$this->position] ?? $this->position;
    }

    public function getSessionsCountAttribute(): int
    {
        return $this->sessions()->count();
    }

    public function getActiveSessionsCountAttribute(): int
    {
        return $this->sessions()
            ->where('last_seen_at', '>=', now()->subMinutes(30))
            ->count();
    }

    public function getConversationsCountAttribute(): int
    {
        return \App\Models\Tenant\Conversation::whereHas('messages', function ($query) {
            // Count conversations that have messages from widget
        })->count();
    }
}