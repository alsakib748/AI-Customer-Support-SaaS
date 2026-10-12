<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class UserPermissionController extends Controller
{
    public function sync(Request $request, User $user)
    {
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->assertCanGrant($request->user(), $data['permissions']);

        $user->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Direct permissions updated.',
            'data' => [
                'permissions' => $user->fresh()->getDirectPermissions()->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Prevent privilege escalation: an administrator may only grant direct
     * permissions they themselves hold. Super Admins hold every permission.
     */
    protected function assertCanGrant(?User $admin, array $permissions): void
    {
        if (! $admin || $admin->isSuperAdmin()) {
            return;
        }

        $ungrantable = array_values(array_filter(
            $permissions,
            fn (string $permission) => ! $admin->hasPermissionTo($permission)
        ));

        if (! empty($ungrantable)) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot grant permissions you do not hold: '.implode(', ', $ungrantable),
            ]);
        }
    }
}
