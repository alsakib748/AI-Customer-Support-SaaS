<?php

namespace App\Http\Resources\ChatWidget;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatWidgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_key' => $this->public_key,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'header_title' => $this->header_title,
            'welcome_message' => $this->welcome_message,
            'offline_message' => $this->offline_message,
            'primary_color' => $this->primary_color,
            'logo' => $this->logo,
            'avatar' => $this->avatar,
            'show_branding' => $this->show_branding,
            'require_name' => $this->require_name,
            'require_email' => $this->require_email,
            'require_phone' => $this->require_phone,
            'allowed_origins' => $this->allowed_origins ?? [],
            'settings' => $this->settings,
            'installation_code' => $this->getInstallationCode(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}