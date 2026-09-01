<?php
// app/Models/TenantInvitation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantInvitation extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_invitations';

    // ⭐ IMPORTANT: Force this model to use central database
    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'invited_by',
        'email',
        'role',
        'department',
        'token',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'status',
        'status_label',
        'status_color',
        'is_pending',
        'is_expired',
        'is_accepted',
        'is_revoked',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusAttribute(): string
    {
        if ($this->accepted_at) {
            return 'accepted';
        }

        if ($this->revoked_at) {
            return 'revoked';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'expired';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsAcceptedAttribute(): bool
    {
        return $this->status === 'accepted';
    }

    public function getIsRevokedAttribute(): bool
    {
        return $this->status === 'revoked';
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'expired' => 'Expired',
            'revoked' => 'Revoked',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'warning',
            'accepted' => 'success',
            'expired' => 'danger',
            'revoked' => 'secondary',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('email', 'ILIKE', "%{$search}%");
    }
}