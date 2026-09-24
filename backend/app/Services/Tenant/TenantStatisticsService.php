<?php

namespace App\Services\Tenant;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

class TenantStatisticsService
{
    /**
     * Aggregated overview for the tenant details page.
     * Runs against ONE tenant only (list page never uses this).
     */
    public function overview(Tenant $tenant): array
    {
        $subscription = $tenant->activeSubscription ?? $tenant->subscriptions()->latest()->first();
        $live         = $this->countLiveData($tenant);

        return [
            'members'       => $tenant->members()->count(),
            'customers'     => $live['customers'],
            'conversations' => $live['conversations'],
            'tickets'       => $live['tickets'],
            'messages'      => $live['messages'],
            'kb_articles'   => $live['kb_articles'],
            'ai_requests'   => (int) ($subscription->ai_requests_used ?? $subscription->ai_used ?? 0),
            'ai_tokens'     => (int) ($subscription->ai_tokens_used ?? 0),
            'storage'       => (int) ($subscription->storage_used ?? 0),
        ];
    }

    public function members(Tenant $tenant): array
    {
        $members = $tenant->members()
            ->with('user')
            ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->get();

        return [
            'total' => $members->count(),
            'by_role' => $members->groupBy('role')->map->count()->toArray(),
            'items' => $members->map(fn (TenantUser $m) => [
                'id'           => $m->id,
                'user_id'      => $m->user_id,
                'name'         => $m->user?->full_name ?? $m->user_name,
                'email'        => $m->user?->email ?? $m->user_email,
                'avatar'       => $m->user?->avatar,
                'role'         => $m->role,
                'role_label'   => $m->role_label,
                'is_owner'     => $m->is_owner,
                'department'   => $m->department,
                'position'     => $m->position,
                'online'       => $m->availability_status === 'online',
                'joined_at'    => $m->accepted_at?->toISOString() ?? $m->created_at?->toISOString(),
            ])->values(),
        ];
    }

    /**
     * Usage tied to the subscription / plan (source of truth is billing).
     */
    public function usage(Tenant $tenant): array
    {
        $subscription = $tenant->activeSubscription ?? $tenant->subscriptions()->latest()->first();

        if (! $subscription) {
            return [
                'plan'   => null,
                'blocks' => [],
            ];
        }

        $pct = fn ($used, $limit) => $limit > 0 ? round((($used ?? 0) / $limit) * 100, 1) : 0;

        $blocks = [
            'ai_requests'    => $this->block($subscription->ai_requests_used ?? $subscription->ai_used, $subscription->ai_limit, $pct),
            'ai_tokens'      => $this->block($subscription->ai_tokens_used, $subscription->ai_tokens_limit, $pct),
            'agents'         => $this->block($subscription->agents_used, $subscription->agents_limit, $pct),
            'customers'      => $this->block($subscription->customers_used, $subscription->customers_limit, $pct),
            'widgets'        => $this->block($subscription->widgets_used, $subscription->widgets_limit, $pct),
            'documents'      => $this->block($subscription->documents_used, $subscription->documents_limit, $pct),
            'kb_articles'    => $this->block($subscription->kb_articles_used, $subscription->kb_articles_limit, $pct),
            'conversations'  => $this->block($subscription->conversations_used, $subscription->conversations_limit, $pct),
            'storage'        => [
                'used'          => (int) $subscription->storage_used,
                'limit'         => (int) $subscription->storage_limit,
                'percentage'    => $pct($subscription->storage_used, $subscription->storage_limit),
                'formatted_used' => $this->formatBytes((int) $subscription->storage_used),
            ],
        ];

        return [
            'plan' => $subscription->plan ? [
                'id'    => $subscription->plan->id,
                'name'  => $subscription->plan->name,
                'slug'  => $subscription->plan->slug,
            ] : null,
            'billing_cycle' => $subscription->billing_cycle,
            'blocks' => $blocks,
        ];
    }

    public function activity(Tenant $tenant, int $limit = 50): array
    {
        return AuditLog::forTenant($tenant->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id'           => $log->id,
                'action'       => $log->action,
                'action_label' => $log->action_label,
                'user_id'      => $log->user_id,
                'user_name'    => $log->user_name,
                'resource_type'=> $log->resource_type,
                'resource_id'  => $log->resource_id,
                'metadata'     => $log->metadata,
                'ip_address'   => $log->ip_address,
                'created_at'   => $log->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    // ============================================
    // INTERNALS
    // ============================================

    protected function block($used, $limit, callable $pct): array
    {
        $used  = (int) ($used ?? 0);
        $limit = (int) ($limit ?? 0);

        return [
            'used'       => $used,
            'limit'      => $limit,
            'remaining'  => $limit > 0 ? max(0, $limit - $used) : null,
            'percentage' => $pct($used, $limit),
            'unlimited'  => $limit <= 0,
        ];
    }

    /**
     * Count live operational rows inside the tenant's own database.
     * Acceptable on the details page (single tenant only), never on lists.
     */
    protected function countLiveData(Tenant $tenant): array
    {
        $zero = [
            'customers'     => 0,
            'conversations' => 0,
            'tickets'       => 0,
            'messages'      => 0,
            'kb_articles'   => 0,
        ];

        try {
            if (! tenancy()->initialized) {
                Tenancy::initialize($tenant);
            }

            return [
                'customers'     => \App\Models\Tenant\Customer::count(),
                'conversations' => \App\Models\Tenant\Conversation::count(),
                'tickets'       => \App\Models\Tenant\Ticket::count(),
                'messages'      => \App\Models\Tenant\Message::count(),
                'kb_articles'   => \App\Models\Tenant\KnowledgeBaseArticle::count(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Tenant live data count failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            return $zero;
        } finally {
            if (tenancy()->initialized) {
                Tenancy::end();
            }
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, $k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}