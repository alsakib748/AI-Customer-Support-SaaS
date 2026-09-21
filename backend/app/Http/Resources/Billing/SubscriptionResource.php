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
            'id'                        => $this->id,
            'uuid'                      => $this->uuid,
            'tenant_id'                 => $this->tenant_id,
            'plan_id'                   => $this->plan_id,
            'status'                    => $this->status,
            'status_label'              => $this->status_label,
            'status_color'              => $this->status_color,
            'billing_cycle'             => $this->billing_cycle,
            'provider'                  => $this->provider,
            'provider_customer_id'      => $this->provider_customer_id,
            'provider_subscription_id'  => $this->provider_subscription_id,
            'provider_price_id'         => $this->provider_price_id,
            'trial_starts_at'           => optional($this->trial_starts_at)->toISOString(),
            'trial_ends_at'             => optional($this->trial_ends_at)->toISOString(),
            'starts_at'                 => optional($this->starts_at)->toISOString(),
            'ends_at'                   => optional($this->ends_at)->toISOString(),
            'current_period_starts_at'  => optional($this->current_period_starts_at)->toISOString(),
            'current_period_ends_at'    => optional($this->current_period_ends_at)->toISOString(),
            'next_billing_at'           => optional($this->next_billing_at)->toISOString(),
            'cancelled_at'              => optional($this->cancelled_at)->toISOString(),
            'auto_renew'                => (bool) $this->auto_renew,
            'cancel_at_period_end'      => (bool) $this->cancel_at_period_end,
            'is_active'                 => $this->is_active,
            'is_trialing'               => $this->is_trialing,
            'is_cancelled'              => $this->is_cancelled,
            'is_expired'                => $this->is_expired,
            'is_on_trial'               => $this->is_on_trial,
            'days_remaining'            => $this->days_remaining,
            'trial_days_remaining'      => $this->trial_days_remaining,
            'usage'                     => $this->formatUsage(),
            'plan'                      => new PlanResource($this->whenLoaded('plan')),
            'metadata'                  => $this->metadata,
            'created_at'                => optional($this->created_at)->toISOString(),
            'updated_at'                => optional($this->updated_at)->toISOString(),
        ];
    }

    protected function formatUsage(): array
    {
        $pct = fn ($used, $limit) => $limit > 0 ? round((($used ?? 0) / $limit) * 100, 1) : 0;

        return [
            'ai'            => $this->usageBlock('ai_requests', $pct),
            'ai_tokens'     => $this->usageBlock('ai_tokens', $pct),
            'agents'        => $this->usageBlock('agents', $pct),
            'customers'     => $this->usageBlock('customers', $pct),
            'widgets'       => $this->usageBlock('widgets', $pct),
            'documents'     => $this->usageBlock('documents', $pct),
            'kb_articles'   => $this->usageBlock('kb_articles', $pct),
            'conversations' => $this->usageBlock('conversations', $pct),
            'storage'       => [
                'used'          => (int) $this->storage_used,
                'limit'         => (int) $this->storage_limit,
                'percentage'    => $pct($this->storage_used, $this->storage_limit),
                'formatted_used'=> $this->formatBytes((int) $this->storage_used),
                'formatted_limit'=> $this->storage_limit > 0
                    ? $this->formatBytes((int) $this->storage_limit) : 'Unlimited',
            ],
        ];
    }

    protected function usageBlock(string $prefix, callable $pct): array
    {
        $used = (int) ($this->{"{$prefix}_used"} ?? 0);
        $limit = (int) ($this->{"{$prefix}_limit"} ?? 0);

        return [
            'used'       => $used,
            'limit'      => $limit,
            'remaining'  => $limit > 0 ? max(0, $limit - $used) : null,
            'percentage' => $pct($used, $limit),
            'unlimited'  => $limit <= 0,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, $k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

}
