<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TenantAssetController extends Controller
{
    /**
     * Serve an asset for a specific tenant.
     *
     * Requires an authenticated user with access to the tenant. The route is
     * protected by the jwt.auth middleware; ownership is verified here so a
     * user cannot read another tenant's stored files.
     *
     * @return Response
     */
    public function show(Request $request, string $tenantId, string $path)
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $user = auth()->user();

        if (! $user || (! $user->isSuperAdmin() && ! $user->hasTenantAccess($tenant->id))) {
            return response()->json(['message' => 'You do not have access to this asset'], 403);
        }

        // Never allow traversal outside the tenant's storage root.
        if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")) {
            return response()->json(['message' => 'Invalid asset path'], 400);
        }

        // Initialize the tenant to ensure the FilesystemTenancyBootstrapper
        // overrides the storage paths for the current request.
        tenancy()->initialize($tenant);

        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        // Return the file using Laravel's built-in response method
        return Storage::disk('public')->response($path);
    }
}
