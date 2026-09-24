<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes, HasFactory;

    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_PROVISIONING_FAILED = 'provisioning_failed';

    // ⭐ IMPORTANT: Force this model to use central database
    protected $connection = 'central';

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
            'suspended_at',
            'archived_at',
            'suspension_reason',
            'provisioned_at',
            'provisioning_error',
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
        'suspended_at',
        'archived_at',
        'suspension_reason',
        'provisioned_at',
        'provisioning_error',
    ];

    protected $casts = [
        'business_hours'       => 'json',
        'settings'             => 'json',
        'metadata'             => 'json',
        'data'                 => 'json',
        'trial_ends_at'        => 'datetime',
        'subscription_ends_at' => 'datetime',
        'suspended_at'         => 'datetime',
        'archived_at'          => 'datetime',
        'provisioned_at'       => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'is_provisioned',
        'is_suspended',
        'is_archived',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Override the Stancl method to use the name column
    public function getTenantName(): string
    {
        return $this->name ?? $this->data['name'] ?? 'Workspace';
    }

    // Relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(
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
            )
            ->withTimestamps();
    }

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function members()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function ownerMembership()
    {
        return $this->hasOne(TenantUser::class)->where('role', 'owner');
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    // ============================================
    // STATUS ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_TRIAL             => 'Trial',
            self::STATUS_ACTIVE            => 'Active',
            self::STATUS_SUSPENDED         => 'Suspended',
            self::STATUS_ARCHIVED          => 'Archived',
            self::STATUS_PROVISIONING      => 'Provisioning',
            self::STATUS_PROVISIONING_FAILED => 'Provisioning Failed',
        ];

        return $labels[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            self::STATUS_TRIAL             => 'info',
            self::STATUS_ACTIVE            => 'success',
            self::STATUS_SUSPENDED         => 'warning',
            self::STATUS_ARCHIVED          => 'secondary',
            self::STATUS_PROVISIONING      => 'warn',
            self::STATUS_PROVISIONING_FAILED => 'danger',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    public function getIsProvisionedAttribute(): bool
    {
        return $this->provisioned_at !== null;
    }

    public function getIsSuspendedAttribute(): bool
    {
        return (bool) $this->suspended_at || $this->status === self::STATUS_SUSPENDED;
    }

    public function getIsArchivedAttribute(): bool
    {
        return (bool) $this->archived_at || $this->status === self::STATUS_ARCHIVED;
    }

    public function isProvisioning(): bool
    {
        return $this->status === self::STATUS_PROVISIONING;
    }

    public function isProvisioningFailed(): bool
    {
        return $this->status === self::STATUS_PROVISIONING_FAILED;
    }

    public function canLogin(): bool
    {
        return $this->status === self::STATUS_ACTIVE || $this->status === self::STATUS_TRIAL;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrial($query)
    {
        return $query->where('status', self::STATUS_TRIAL);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCreatedBetween($query, $from, $to = null)
    {
        return $query->whereBetween('created_at', [$from, $to ?? now()]);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('slug', 'ilike', "%{$search}%")
                ->orWhere('industry', 'ilike', "%{$search}%")
                ->orWhere('support_email', 'ilike', "%{$search}%")
                ->orWhereHas('ownerMembership.user', function ($user) use ($search) {
                    $user->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
        });
    }

    public function scopeWithPlan($query, $planId)
    {
        return $query->whereHas('subscriptions', fn ($q) => $q->where('plan_id', $planId));
    }

    public function scopeWithSubscriptionStatus($query, $status)
    {
        return $query->whereHas('subscriptions', fn ($q) => $q->where('status', $status));
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrialExpired(): bool
    {
        if (! $this->trial_ends_at) {
            return false;
        }
        return $this->trial_ends_at->isPast();
    }

    public function getOwner()
    {
        return $this->tenantUsers()
            ->where('role', 'owner')
            ->first()?->user;
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

    // Billing & Subscriptions

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Subscription::class, 'tenant_id');
    }

public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Models\Subscription::class, 'tenant_id')
        ->whereIn('status', ['trialing', 'active'])
        ->latestOfMany();
}


}