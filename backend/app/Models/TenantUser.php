<?php
// app/Models/TenantUser.php

namespace App\Models;

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
        'permissions' => 'json',
        'skills' => 'json',
        'metadata' => 'json',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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
}
