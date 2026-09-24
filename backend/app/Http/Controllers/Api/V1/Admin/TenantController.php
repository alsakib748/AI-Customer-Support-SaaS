<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Resources\Admin\TenantDetailResource;
use App\Http\Resources\Admin\TenantMemberResource;
use App\Http\Resources\Admin\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\Tenant\TenantListService;
use App\Services\Tenant\TenantProvisioningService;
use App\Services\Tenant\TenantStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(
        protected TenantListService $list,
        protected TenantProvisioningService $provisioning,
        protected TenantStatisticsService $statistics,
        protected AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'status',
            'plan_id',
            'subscription_status',
            'created_from',
            'created_to',
            'sort',
            'direction',
            'per_page',
        ]);

        $tenants = $this->list->paginate($filters);

        return TenantResource::collection($tenants);
    }

    public function store(CreateTenantRequest $request)
    {
        $validated = $request->validated();

        $tenant = $this->provisioning->provision(
            $this->createTenantRecord($validated),
            $validated
        );

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Tenant created and provisioned successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Tenant $tenant)
    {
        $tenant->load([
            'ownerMembership.user',
            'activeSubscription.plan',
        ]);

        $statistics = [
            'overview' => $this->statistics->overview($tenant),
            'usage'    => $this->statistics->usage($tenant),
        ];

        return new TenantDetailResource($tenant, $statistics);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $validated = $request->validated();

        $old = $tenant->only(['name', 'slug', 'subdomain', 'domain', 'industry', 'timezone', 'support_email']);

        $tenant->update($validated);

        $this->auditLog->log(
            'tenant.updated',
            'tenant',
            $tenant->id,
            $old,
            $validated
        );

        return (new TenantResource($tenant->fresh(['ownerMembership.user', 'activeSubscription.plan'])))
            ->additional(['message' => 'Tenant updated successfully.']);
    }

    public function members(Tenant $tenant)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->statistics->members($tenant),
        ]);
    }

    public function usage(Tenant $tenant)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->statistics->usage($tenant),
        ]);
    }

    public function activity(Request $request, Tenant $tenant)
    {
        $limit = min((int) $request->input('limit', 50), 100);

        return response()->json([
            'success' => true,
            'data'    => $this->statistics->activity($tenant, $limit),
        ]);
    }

    public function plans()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'trial_days', 'is_default']);

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    // ============================================
    // INTERNALS
    // ============================================

    protected function createTenantRecord(array $validated): Tenant
    {
        $slug = $validated['slug']
            ?? Str::slug($validated['name']) . '-' . Str::lower(Str::random(5));

        $subdomain = $validated['subdomain']
            ?? Str::lower(Str::slug($validated['name'])) . '-' . Str::lower(Str::random(5));

        $settings = array_merge([
            'ai_enabled'    => true,
            'max_agents'    => 5,
            'locale'        => 'en',
        ], $validated['settings'] ?? []);

        return Tenant::create([
            'name'             => $validated['name'],
            'slug'             => Str::slug($slug),
            'subdomain'        => $subdomain,
            'domain'           => $validated['domain'] ?? null,
            'industry'         => $validated['industry'] ?? null,
            'timezone'         => $validated['timezone'] ?? 'UTC',
            'default_language' => $validated['default_language'] ?? 'en',
            'support_email'    => $validated['support_email'] ?? null,
            'support_phone'    => $validated['support_phone'] ?? null,
            'business_hours'   => $validated['business_hours'] ?? null,
            'settings'         => $settings,
            'status'           => Tenant::STATUS_PROVISIONING,
            'data'             => [],
        ]);
    }
}