<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasFactory, Notifiable, SoftDeletes, HasUuids, HasRoles;

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
            ->withPivot('role', 'permissions', 'department', 'availability_status', 'skills')
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
        return $this->hasRole('super-admin');
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

    // Spatie Permission team integration
    public function getTeamId()
    {
        return $this->current_tenant_id;
    }

    public function hasPermissionTo($permission, $guardName = null)
    {
        $permission = $this->getPermission($permission, $guardName);

        if (!$permission) {
            return false;
        }

        // Check if user has permission directly or through roles
        return $this->hasDirectPermission($permission)
            || $this->hasPermissionViaRole($permission);
    }

    // For Spatie Permission with teams
    protected function getPermission($permission, $guardName)
    {
        $className = config('permission.models.permission');

        $query = (new $className)->where(function ($query) use ($permission, $guardName) {
            $query->where('name', $permission);

            if ($guardName) {
                $query->where('guard_name', $guardName);
            }
        });

        // Scope permission to current tenant if teams are enabled
        if (config('permission.teams')) {
            $query->where('team_foreign_key', $this->getTeamId());
        }

        return $query->first();
    }

}
