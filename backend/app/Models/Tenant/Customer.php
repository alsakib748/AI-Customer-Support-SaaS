<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    // protected $connection = 'tenant';

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'avatar',
        'status',
        'metadata',
        'tags',
        'notes',
        'default_language',
        'timezone',
        'last_contacted_at',
        'total_conversations',
        'total_tickets',
        'satisfaction_score',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
        'total_conversations' => 'integer',
        'total_tickets' => 'integer',
        'satisfaction_score' => 'decimal:2',
    ];

    protected $appends = [
        'full_name',
        'display_name',
        'initials',
        'status_label',
        'status_color',
    ];

    // ============================================
    // ACCESSORS
    // ============================================
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->email ?? $this->phone ?? 'Customer #' . $this->id;
    }

    public function getInitialsAttribute(): string
    {
        $first = substr($this->first_name, 0, 1);
        $last = $this->last_name ? substr($this->last_name, 0, 1) : '';
        return strtoupper($first . $last);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'blocked' => 'Blocked',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'active' => 'success',
            'inactive' => 'secondary',
            'blocked' => 'danger',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    //  Add avatar accessor for frontend compatibility
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? '/storage/' . $this->avatar : null;
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'ILIKE', "%{$search}%")
                ->orWhere('last_name', 'ILIKE', "%{$search}%")
                ->orWhere('email', 'ILIKE', "%{$search}%")
                ->orWhere('phone', 'ILIKE', "%{$search}%")
                ->orWhere('company_name', 'ILIKE', "%{$search}%");
        });
    }

    public function scopeByTag($query, $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    // ============================================
    // METHODS
    // ============================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function block(string $reason = null): void
    {
        $this->update([
            'status' => 'blocked',
            'notes' => $this->notes ? $this->notes . "\n\nBlocked: " . ($reason ?? 'No reason provided') : 'Blocked: ' . ($reason ?? 'No reason provided'),
        ]);
    }

    public function unblock(): void
    {
        $this->update(['status' => 'active']);
    }

    public function incrementConversations(): void
    {
        $this->increment('total_conversations');
        $this->update(['last_contacted_at' => now()]);
    }

    public function incrementTickets(): void
    {
        $this->increment('total_tickets');
        $this->update(['last_contacted_at' => now()]);
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, function ($t) use ($tag) {
            return $t !== $tag;
        });
        $this->update(['tags' => array_values($tags)]);
    }

}
