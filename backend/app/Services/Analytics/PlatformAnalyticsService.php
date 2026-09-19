<?php
// app/Services/Analytics/PlatformAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlatformAnalyticsService
{
    /**
     * Central-level platform metrics.
     */
    public function overview(): array
    {
        $cacheKey = 'analytics:platform:overview';

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::warning('Cache get failed in platform overview, bypassing cache', ['error' => $e->getMessage()]);
        }

        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $trialTenants = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();

        $data = [
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

        try {
            Cache::put($cacheKey, $data, now()->addSeconds(60));
        } catch (\Throwable $e) {
            Log::warning('Cache put failed in platform overview', ['error' => $e->getMessage()]);
        }

        return $data;
    }

    /**
     * Aggregated usage per tenant — expensive; use explicit opt-in.
     */
    public function tenantUsage(): array
    {
        $cacheKey = 'analytics:platform:tenant_usage';

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::warning('Cache get failed in tenant usage, bypassing cache', ['error' => $e->getMessage()]);
        }

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

        try {
            Cache::put($cacheKey, $usage, now()->addMinutes(5));
        } catch (\Throwable $e) {
            Log::warning('Cache put failed in tenant usage', ['error' => $e->getMessage()]);
        }

        return $usage;
    }
}