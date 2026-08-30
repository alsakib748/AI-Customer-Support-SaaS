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
     * @param  string  $tenantId
     * @param  string  $path
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function show(string $tenantId, string $path)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        // Initialize the tenant to ensure the FilesystemTenancyBootstrapper
        // overrides the storage paths for the current request.
        tenancy()->initialize($tenant);

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        // Return the file using Laravel's built-in response method
        return Storage::disk('public')->response($path);
    }
}
