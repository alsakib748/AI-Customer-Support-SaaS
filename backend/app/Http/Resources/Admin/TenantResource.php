<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the tenant resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->activeSubscription ?? $this->subscriptions->first();

        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'slug'                => $this->slug,
            'subdomain'           => $this->subdomain,
            'domain'              => $this->domain,
            'logo'                => $this->logo,
            'favicon'             => $this->favicon,
            'industry'            => $this->industry,
            'timezone'            => $this->timezone,
            'default_language'    => $this->default_language,
            'support_email'       => $this->support_email,
            'support_phone'       => $this->support_phone,
            'settings'            => $this->settings,
            'status'              => $this->status,
            'status_label'        => $this->status_label,
            'status_color'        => $this->status_color,
            'is_provisioned'      => $this->is_provisioned,
            'is_suspended'        => $this->is_suspended,
            'is_archived'         => $this->is_archived,
            'trial_ends_at'       => optional($this->trial_ends_at)->toISOString(),
            'subscription_ends_at'=> optional($this->subscription_ends_at)->toISOString(),
            'suspended_at'        => optional($this->suspended_at)->toISOString(),
            'archived_at'         => optional($this->archived_at)->toISOString(),
            'suspension_reason'   => $this->suspension_reason,
            'provisioned_at'      => optional($this->provisioned_at)->toISOString(),
            'provisioning_error'  => $this->provisioning_error,
            'owner'               => $this->whenLoaded('ownerMembership', fn () => [
                'id'    => $this->ownerMembership->user_id,
                'name'  => $this->ownerMembership->user->full_name ?? null,
                'email' => $this->ownerMembership->user->email ?? null,
                'avatar'=> $this->ownerMembership->user->avatar ?? null,
            ]),
            'members_count'       => $this->members_count,
            'subscription'        => $this->whenLoaded('activeSubscription', fn () => $subscription ? [
                'id'             => $subscription->id,
                'status'         => $subscription->status,
                'status_label'   => $subscription->status_label,
                'status_color'   => $subscription->status_color,
                'billing_cycle'  => $subscription->billing_cycle,
                'plan'           => $subscription->plan ? [
                    'id'   => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'price_monthly'  => $subscription->plan->price_monthly,
                    'price_yearly'   => $subscription->plan->price_yearly,
                    'trial_days'     => $subscription->plan->trial_days,
                ] : null,
                'current_period_ends_at' => optional($subscription->current_period_ends_at)->toISOString(),
                'next_billing_at'        => optional($subscription->next_billing_at)->toISOString(),
            ] : null),
            'created_at'          => optional($this->created_at)->toISOString(),
            'updated_at'          => optional($this->updated_at)->toISOString(),
        ];
    }
}