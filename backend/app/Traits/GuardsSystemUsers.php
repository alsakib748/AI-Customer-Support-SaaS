<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait GuardsSystemUsers
{
    /**
     * Reject any attempt to modify an account holding the super_admin role.
     * Returns a 403 JSON response when the target user is protected.
     */
    protected function protectSystemUser(User $user): ?JsonResponse
    {
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin accounts are protected by the system and cannot be modified.',
            ], 403);
        }

        return null;
    }
}