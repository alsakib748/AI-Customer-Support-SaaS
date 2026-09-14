<?php
// app/Services/Analytics/PlatformAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PlatformAnalyticsService
{
    /**
     * Central-level platform metrics.
     */
    public function overview(): array
    {
        $cacheKey = 'analytics:platform:overview';

        return Cache::remember($cacheKey, now()->addSeconds(60), function () {
            $totalTenants = Tenant::count();
            $activeTenants = Tenant::where('status', 'active')->count();
            $trialTenants = Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count();
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();

            return [
                'tenants' => [
                    'total' => $totalTenants,
                    'active' => $activeTenants,
                    'trial' => $trialTenants,
                ],
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                ],
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Aggregated usage per tenant — expensive; use explicit opt-in.
     */
    public function tenantUsage(): array
    {
        return Cache::remember('analytics:platform:tenant_usage', now()->addMinutes(5), function () {
            $tenants = Tenant::select('id', 'name')->limit(50)->get();

            $usage = [];
            foreach ($tenants as $tenant) {
                try {
                    \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);

                    $usage[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'conversations' => \App\Models\Tenant\Conversation::count(),
                        'customers' => \App\Models\Tenant\Customer::count(),
                        'tickets' => \App\Models\Tenant\Ticket::count(),
                        'ai_requests' => \App\Models\Tenant\AIUsage::count(),
                    ];
                } catch (\Throwable $e) {
                    $usage[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'error' => $e->getMessage(),
                    ];
                } finally {
                    \Stancl\Tenancy\Facades\Tenancy::end();
                }
            }

            return $usage;
        });
    }
}