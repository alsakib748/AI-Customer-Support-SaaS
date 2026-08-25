<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission): Response
    {

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'code' => 401,
            ], 401);
        }

        // Check if user has the permission
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to {$permission}",
                'permission' => $permission,
                'code' => 403,
            ], 403);
        }

        return $next($request);
    }
}
