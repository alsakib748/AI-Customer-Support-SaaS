<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes;

    // Tell Stancl which columns are real DB columns (not packed into the central 'data' JSON)
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'subdomain',
            'domain',
            'logo',
            'favicon',
            'industry',
            'timezone',
            'default_language',
            'support_email',
            'support_phone',
            'business_hours',
            'settings',
            'status',
            'metadata',
            'trial_ends_at',
            'subscription_ends_at',
        ];
    }

    protected $fillable = [
        'id', // UUID from Stancl
        'name',
        'slug',
        'subdomain',
        'domain',
        'logo',
        'favicon',
        'industry',
        'timezone',
        'default_language',
        'support_email',
        'support_phone',
        'business_hours',
        'settings',
        'status',
        'metadata',
        'data',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'business_hours' => 'json',
        'settings' => 'json',
        'metadata' => 'json',
        'data' => 'json',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    // Override the Stancl method to use the name column
    public function getTenantName(): string
    {
        return $this->name ?? $this->data['name'] ?? 'Workspace';
    }

    // Relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role', 'permissions', 'department', 'availability_status', 'skills', 'metadata')
            ->withTimestamps();
    }

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrialExpired(): bool
    {
        if (!$this->trial_ends_at) {
            return false;
        }
        return $this->trial_ends_at->isPast();
    }

    public function getOwner()
    {
        return $this->tenantUsers()
            ->where('role', 'owner')
            ->first()
                ?->user;
    }

    public function getSetting($key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting($key, $value)
    {
        $settings = $this->settings ?: [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    // Stancl required method
    public function getTenantKeyName(): string
    {
        return 'id'; // UUID
    }

    public function getTenantKey()
    {
        return $this->id;
    }

    public function getTenantConnection()
    {
        return 'tenant';
    }

}