<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    /**
     * Full platform-identity record for the user details page.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prefs = is_array($this->preferences) ? $this->preferences : [];

        return [
            'id'                    => $this->id,
            'uuid'                  => $this->uuid,
            'name'                  => $this->full_name,
            'first_name'            => $this->first_name,
            'last_name'             => $this->last_name,
            'username'              => $this->username,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'company_name'          => $this->company_name,
            'avatar'                => $this->avatar,
            'timezone'              => $this->timezone,
            'language'              => $this->language,
            'status'                => $this->status,
            'status_label'          => $this->status_label,
            'status_color'          => $this->status_color,
            'is_active'             => (bool) $this->is_active,
            'scope'                 => $this->scope,
            'is_super_admin'        => $this->isSuperAdmin(),
            'roles'                 => $this->getRoleNames()->values()->all(),
            'email_verified'        => (bool) $this->email_verified_at,
            'email_verified_at'     => optional($this->email_verified_at)->toISOString(),
            'last_login_at'         => optional($this->last_login_at)->toISOString(),
            'last_login_ip'         => $this->last_login_ip,
            'current_tenant_id'     => $this->current_tenant_id,
            'tenant_count'          => (int) ($this->tenant_users_count ?? $this->tenantUsers()->count()),
            'must_change_password'  => (bool) ($prefs['must_change_password'] ?? false),
            'sessions_revoked_at'   => $prefs['sessions_revoked_at'] ?? null,
            'suspended_at'          => $prefs['suspended_at'] ?? null,
            'suspension_reason'     => $prefs['suspension_reason'] ?? null,
            'created_at'            => optional($this->created_at)->toISOString(),
            'updated_at'            => optional($this->updated_at)->toISOString(),
            'deleted_at'            => optional($this->deleted_at)->toISOString(),
        ];
    }
}