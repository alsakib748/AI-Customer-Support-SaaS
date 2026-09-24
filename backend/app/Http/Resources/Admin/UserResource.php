<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the platform user into an array (list item payload).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'name'            => $this->full_name,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'email'           => $this->email,
            'avatar'          => $this->avatar,
            'status'          => $this->status,
            'status_label'    => $this->status_label,
            'status_color'    => $this->status_color,
            'scope'           => $this->scope,
            'is_super_admin'  => $this->isSuperAdmin(),
            'email_verified'  => (bool) $this->email_verified_at,
            'tenant_count'    => (int) ($this->tenant_users_count ?? 0),
            'last_login_at'   => optional($this->last_login_at)->toISOString(),
            'created_at'      => optional($this->created_at)->toISOString(),
            'updated_at'      => optional($this->updated_at)->toISOString(),
        ];
    }
}