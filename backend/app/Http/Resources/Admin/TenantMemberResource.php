<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantMemberResource extends JsonResource
{
    /**
     * Transform the tenant member (TenantUser) resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenant_id,
            'user_id'      => $this->user_id,
            'name'         => $this->user_name,
            'email'        => $this->user_email,
            'avatar'       => $this->avatar,
            'role'         => $this->role,
            'role_label'   => $this->role_label,
            'is_owner'     => $this->is_owner,
            'department'   => $this->department,
            'position'     => $this->position,
            'availability_status'     => $this->availability_status,
            'availability_status_label'=> $this->availability_status_label,
            'availability_status_color'=> $this->availability_status_color,
            'max_concurrent_chats'     => $this->max_concurrent_chats,
            'skills'                   => $this->skills,
            'invited_at'               => optional($this->invited_at)->toISOString(),
            'accepted_at'              => optional($this->accepted_at)->toISOString(),
            'created_at'               => optional($this->created_at)->toISOString(),
            'updated_at'               => optional($this->updated_at)->toISOString(),
        ];
    }
}