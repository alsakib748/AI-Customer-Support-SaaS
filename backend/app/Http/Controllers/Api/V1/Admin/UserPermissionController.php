<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class UserPermissionController extends Controller
{
    public function sync(Request $request, User $user)
    {
        $data = $request->validate([
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Direct permissions updated.',
            'data'    => [
                'permissions' => $user->fresh()->getDirectPermissions()->pluck('name')->toArray(),
            ],
        ]);
    }
}