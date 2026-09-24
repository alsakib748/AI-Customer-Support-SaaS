<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable([
    'first_name',
    'last_name',
    'username',
    'email',
    'password',
    'phone',
    'company_name',
    'avatar',
    'preferences',
    'timezone',
    'language',
    'is_active',
    'last_login_at',
    'last_login_ip',
    'current_tenant_id',
    'uuid',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    // ⭐ IMPORTANT: Force this model to use central database
    protected $connection = 'central';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'json',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['full_name', 'display_name'];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'user_id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'tenant_id' => $this->current_tenant_id,
            'full_name' => $this->full_name,
        ];
    }

    // If you want to use a different column for UUID:
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // Or if you want to generate UUIDs for multiple columns:
    public function getUuidColumns(): array
    {
        return ['uuid'];
    }

    // Relationships
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot(
                'role',
                'department',
                'position',
                'availability_status',
                'max_concurrent_chats',
                'skills',
                'metadata',
                'invited_at',
                'accepted_at',
                'deleted_at',
            )
            ->withTimestamps();
    }

    public function currentTenant()
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getDisplayNameAttribute()
    {
        return $this->full_name ?: $this->email;
    }

    // Status is derived from is_active — the single authentication gate.
    // No separate status column is used (see suspend/activate lifecycle).
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'suspended';
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'active' ? 'Active' : 'Suspended';
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status === 'active' ? 'success' : 'danger';
    }

    /**
     * Platform scope = super_admin role (no tenant membership required).
     * Tenant scope = a normal user (owner/manager/etc. inside one or more tenants).
     */
    public function getScopeAttribute(): string
    {
        return $this->isSuperAdmin() ? 'platform' : 'tenant';
    }

    /**
     * Read a single value from the preferences JSON column.
     */
    public function preference(string $key, $default = null)
    {
        $preferences = is_array($this->preferences) ? $this->preferences : [];

        return $preferences[$key] ?? $default;
    }

    public function setPreference(string $key, $value): void
    {
        $preferences          = is_array($this->preferences) ? $this->preferences : [];
        $preferences[$key]    = $value;
        $this->preferences    = $preferences;
    }

    /**
     * Whether a JWT issued at the given unix timestamp is stale because the
     * user's sessions were revoked server-side (stored in preferences).
     */
    public function isSessionRevokedBefore(int $issuedAt): bool
    {
        $revokedAt = $this->preference('sessions_revoked_at');

        if (! $revokedAt) {
            return false;
        }

        try {
            return $issuedAt < \Illuminate\Support\Carbon::parse($revokedAt)->getTimestamp();
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    // Methods
    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    public function hasTenantAccess($tenantId)
    {
        return $this->tenants()->where('tenant_id', $tenantId)->exists();
    }

    public function getCurrentTenantId()
    {
        return $this->current_tenant_id;
    }

    public function setCurrentTenant($tenantId)
    {
        if ($this->hasTenantAccess($tenantId)) {
            $this->update(['current_tenant_id' => $tenantId]);
            return true;
        }
        return false;
    }

    // Stancl - Check if user belongs to current tenant
    public function belongsToTenant($tenant = null)
    {
        $tenant = $tenant ?: tenant();
        if (!$tenant) {
            return false;
        }
        return $this->tenants()->where('tenant_id', $tenant->id)->exists();
    }

}