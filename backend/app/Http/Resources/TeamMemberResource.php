<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle case where user relationship might not be loaded
        $user = $this->whenLoaded('user');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $user ? $user->full_name : $this->user_name,
            'email' => $user ? $user->email : $this->user_email,
            'avatar' => $user ? $user->avatar : null,
            'role' => $this->role,
            'role_label' => $this->role_label,
            'is_owner' => $this->is_owner,
            'department' => $this->department,
            'position' => $this->position,
            'availability_status' => $this->availability_status,
            'availability_status_label' => $this->availability_status_label,
            'availability_status_color' => $this->availability_status_color,
            'max_concurrent_chats' => (int) $this->max_concurrent_chats,
            'skills' => $this->skills ?? [],
            'invited_at' => $this->invited_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            // Add Spatie permissions if user exists
            'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            'roles' => $user ? $user->getRoleNames() : [],
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
