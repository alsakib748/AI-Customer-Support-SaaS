<?php
// app/Models/TenantUser.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantUser extends Pivot
{
    protected $table = 'tenant_user';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'permissions',
        'department',
        'position',
        'availability_status',
        'max_concurrent_chats',
        'skills',
        'metadata',
        'invited_at',
        'accepted_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'skills' => 'array',
        'metadata' => 'array',
        'max_concurrent_chats' => 'integer',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected $appends = [
        'user_name',
        'user_email',
        'user_avatar',
        'is_owner',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function owner()
    {
        return $this->hasOne(TenantUser::class)
            ->where('role', 'owner');
    }

    public function admins()
    {
        return $this->hasMany(TenantUser::class)
            ->whereIn('role', ['owner', 'admin']);
    }

    public function agents()
    {
        return $this->hasMany(TenantUser::class)
            ->where('role', 'agent');
    }

    public function onlineAgents()
    {
        return $this->agents()
            ->where('availability_status', 'online');
    }

    public function currentTenantUser()
    {
        return $this->hasOne(TenantUser::class)
            ->where('tenant_id', $this->current_tenant_id);
    }

    public function getCurrentTenantUserAttribute()
    {
        return $this->tenantUsers()
            ->where('tenant_id', $this->current_tenant_id)
            ->first();
    }

    public function isMemberOfTenant(string $tenantId): bool
    {
        return $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot([
                'role',
                'permissions',
                'department',
                'position',
                'availability_status',
                'max_concurrent_chats',
                'skills',
                'metadata',
                'invited_at',
                'accepted_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getUserNameAttribute(): string
    {
        return $this->user?->full_name ?? $this->user?->email ?? 'Unknown User';
    }

    public function getUserEmailAttribute(): string
    {
        return $this->user?->email ?? '';
    }

    public function getUserAvatarAttribute(): ?string
    {
        return $this->user?->avatar;
    }

    public function getIsOwnerAttribute(): bool
    {
        return $this->role === 'owner';
    }

    public function getAvailabilityStatusLabelAttribute(): string
    {
        $labels = [
            'online' => 'Online',
            'offline' => 'Offline',
            'away' => 'Away',
            'busy' => 'Busy',
        ];

        return $labels[$this->availability_status] ?? $this->availability_status;
    }

    public function getAvailabilityStatusColorAttribute(): string
    {
        $colors = [
            'online' => 'success',
            'offline' => 'secondary',
            'away' => 'warning',
            'busy' => 'danger',
        ];

        return $colors[$this->availability_status] ?? 'secondary';
    }

    public function getRoleLabelAttribute(): string
    {
        $labels = [
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'agent' => 'Support Agent',
            'viewer' => 'Viewer',
        ];

        return $labels[$this->role] ?? $this->role;
    }

    public function getSkillsListAttribute(): array
    {
        return $this->skills ?? [];
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOnline($query)
    {
        return $query->where('availability_status', 'online');
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('availability_status', ['online', 'away']);
    }

    public function scopeWithDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeWithRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            })
                ->orWhere('department', 'ILIKE', "%{$search}%")
                ->orWhere('position', 'ILIKE', "%{$search}%");
        });
    }

    // ============================================
    // METHODS
    // ============================================

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    public function isAdmin()
    {
        return in_array($this->role, ['owner', 'admin']);
    }

    public function isAgent()
    {
        return $this->role === 'agent';
    }

    public function hasPermission($permission)
    {
        if ($this->isOwner()) {
            return true;
        }

        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    public function isAvailable(): bool
    {
        return in_array($this->availability_status, ['online', 'away']);
    }

    public function canAcceptMoreChats(): bool
    {
        // This will be implemented with conversation counting later
        return true;
    }

}