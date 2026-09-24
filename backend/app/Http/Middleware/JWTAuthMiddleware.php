<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JWTAuth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JWTAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired. Please refresh your token.',
                'error' => 'token_expired',
                'code' => 401
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid. Please login again.',
                'error' => 'token_invalid',
                'code' => 401
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided. Please login.',
                'error' => 'token_absent',
                'code' => 401
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found. Please login again.',
                'error' => 'user_not_found',
                'code' => 401
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
                'error' => 'account_inactive',
                'code' => 403
            ], 403);
        }

        // Reject tokens issued before the user's sessions were revoked
        // server-side (see UserSessionService). This makes stateless JWT
        // revocation work without a token table.
        try {
            $payload = JWTAuth::getPayload();
            if ($user->isSessionRevokedBefore((int) $payload->get('iat'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session has been revoked. Please sign in again.',
                    'error' => 'session_revoked',
                    'code' => 401
                ], 401);
            }
        } catch (JWTException $e) {
            // Payload unreadable — let the downstream chain decide.
        }

        auth()->setUser($user);

        return $next($request);
    }
}
