<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'plan' => $this->plan ? new PlanResource($this->plan) : null,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'billing_cycle' => $this->billing_cycle,
            'trial_starts_at' => $this->trial_starts_at?->toISOString(),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'paused_at' => $this->paused_at?->toISOString(),
            'auto_renew' => $this->auto_renew,
            'next_billing_at' => $this->next_billing_at?->toISOString(),
            'last_billing_at' => $this->last_billing_at?->toISOString(),
            'is_active' => $this->is_active,
            'is_trialing' => $this->is_trialing,
            'is_cancelled' => $this->is_cancelled,
            'is_expired' => $this->is_expired,
            'is_on_trial' => $this->is_on_trial,
            'days_remaining' => $this->days_remaining,
            'trial_days_remaining' => $this->trial_days_remaining,
            'items' => SubscriptionItemResource::collection($this->whenLoaded('items')),
            'additional_charges' => $this->additional_charges,
            'grand_total' => $this->grand_total,
            'usage' => [
                'ai' => [
                    'used' => $this->ai_used,
                    'limit' => $this->ai_limit,
                    'percentage' => $this->usage_percentage['ai'],
                ],
                'agents' => [
                    'used' => $this->agents_used,
                    'limit' => $this->agents_limit,
                    'percentage' => $this->usage_percentage['agents'],
                ],
                'documents' => [
                    'used' => $this->documents_used,
                    'limit' => $this->documents_limit,
                    'percentage' => $this->usage_percentage['documents'],
                ],
                'storage' => [
                    'used' => $this->storage_used,
                    'limit' => $this->storage_limit,
                    'percentage' => $this->usage_percentage['storage'],
                ],
                'conversations' => [
                    'used' => $this->conversations_used,
                    'limit' => $this->conversations_limit,
                    'percentage' => $this->usage_percentage['conversations'],
                ],
            ],
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function formatUsage(string $key, string $fieldPrefix): array
    {
        $used = (int) ($this->{"{$fieldPrefix}_used"} ?? 0);
        $limit = (int) ($this->{"{$fieldPrefix}_limit"} ?? 0);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
            'percentage' => $limit > 0 ? round(($used / $limit) * 100, 1) : 0,
            'unlimited' => $limit <= 0,
        ];
    }

    protected function formatStorage(string $fieldPrefix): array
    {
        return $this->formatUsage($fieldPrefix, 'storage');
    }

}