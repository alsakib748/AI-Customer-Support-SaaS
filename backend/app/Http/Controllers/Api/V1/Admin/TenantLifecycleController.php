<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchiveTenantRequest;
use App\Http\Requests\Admin\SuspendTenantRequest;
use App\Http\Requests\Admin\TransferTenantOwnerRequest;
use App\Http\Resources\Admin\TenantResource;
use App\Models\Tenant;
use App\Services\Tenant\TenantAccessService;
use App\Services\Tenant\TenantLifecycleService;
use App\Services\Tenant\TenantProvisioningService;
use Illuminate\Http\Request;

class TenantLifecycleController extends Controller
{
    public function __construct(
        protected TenantLifecycleService $lifecycle,
        protected TenantProvisioningService $provisioning,
        protected TenantAccessService $access,
    ) {
    }

    public function activate(Tenant $tenant)
    {
        $tenant = $this->lifecycle->activate($tenant);

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Tenant activated successfully.']);
    }

    public function suspend(SuspendTenantRequest $request, Tenant $tenant)
    {
        $tenant = $this->lifecycle->suspend($tenant, $request->input('reason'));

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Tenant suspended successfully.']);
    }

    public function archive(ArchiveTenantRequest $request, Tenant $tenant)
    {
        $tenant = $this->lifecycle->archive($tenant, $request->input('confirmation'));

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Tenant archived successfully.']);
    }

    public function restore(Tenant $tenant)
    {
        $tenant = $this->lifecycle->restore($tenant);

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Tenant restored successfully.']);
    }

    public function retryProvisioning(Tenant $tenant)
    {
        $tenant = $this->provisioning->retry($tenant);

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Provisioning retried.']);
    }

    public function transferOwner(TransferTenantOwnerRequest $request, Tenant $tenant)
    {
        $tenant = $this->lifecycle->transferOwner($tenant, $request->input('user_id'));

        return (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan')))
            ->additional(['message' => 'Tenant ownership transferred successfully.']);
    }

    public function manage(Tenant $tenant)
    {
        $tenant = $this->access->enterContext($tenant);

        return response()->json([
            'success' => true,
            'message' => 'Now managing tenant context.',
            'data'    => (new TenantResource($tenant->load('ownerMembership.user', 'activeSubscription.plan'))),
        ]);
    }

    public function exitContext()
    {
        $this->access->exitContext();

        return response()->json([
            'success' => true,
            'message' => 'Exited tenant context.',
        ]);
    }
}