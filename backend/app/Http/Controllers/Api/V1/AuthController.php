<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\EmailNotVerifiedException;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    protected AuthService $authService;

    protected AuditLogService $auditLogService;

    public function __construct(AuthService $authService, AuditLogService $auditLogService)
    {
        $this->authService = $authService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {

        try {
            $result = $this->authService->register($request->all());

            // Log registration
            $this->auditLogService->log(
                'user_registered',
                'user',
                $result['user']['id'],
                null,
                [
                    'email' => $result['user']['email'],
                    'company' => $result['tenant']['name'],
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Welcome to AI Support SaaS!',
                'data' => $result,
            ], 201);
        } catch (ValidationException $e) {

            // Log failed registration attempt
            $this->auditLogService->log(
                'user_registration_failed',
                null,
                null,
                null,
                ['email' => $request->email],
                ['reason' => 'Validation failed']
            );

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login user
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        try {

            $result = $this->authService->login($request->only(['email', 'password']));

            // Log successful login
            $this->auditLogService->log(
                'user_logged_in',
                'user',
                $result['user']['id'],
                null,
                [
                    'email' => $result['user']['email'],
                    'ip' => $request->ip(),
                ],
                [
                    'login_method' => 'password',
                    'user_agent' => $request->userAgent(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful! Welcome back, '.$result['user']['first_name'],
                'data' => $result,
            ]);
        } catch (EmailNotVerifiedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'email_not_verified',
            ], 403);
        } catch (ValidationException $e) {
            Log::warning('Login validation failed', ['errors' => $e->errors()]);

            // Log failed login attempt
            $this->auditLogService->log(
                'user_login_failed',
                'user',
                null,
                null,
                ['email' => $request->email],
                ['reason' => 'Invalid credentials']
            );

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login error:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'errors' => ['general' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Logout user
     *
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user) {
                $this->auditLogService->log(
                    'user_logged_out',
                    'user',
                    $user->id,
                    null,
                    ['email' => $user->email]
                );
            }

            $this->authService->logout();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh JWT token
     *
     * @return JsonResponse
     */
    public function refresh(Request $request)
    {
        try {
            $token = $this->authService->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                ],
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token. Please login again.',
                'error' => 'token_refresh_failed',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user details
     *
     * @return JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $data = $this->authService->me();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not fetch user details: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change user password
     *
     * @return JsonResponse
     */
    public function changePassword(Request $request)
    {
        try {
            $user = auth()->user();
            $newToken = $this->authService->changePassword($user, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'data' => [
                    'token' => $newToken,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate token validity
     *
     * @return JsonResponse
     */
    public function validateToken(Request $request)
    {
        try {
            $token = $request->bearerToken();

            if (! $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided',
                    'valid' => false,
                ], 400);
            }

            $result = $this->authService->validateToken($token);

            if ($result['valid']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Token is valid',
                    'data' => [
                        'valid' => true,
                        'user' => $result['user'],
                        'expires_at' => $result['expires_at'],
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'valid' => false,
                    'error' => $result['error'] ?? null,
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token validation failed: '.$e->getMessage(),
                'valid' => false,
            ], 500);
        }
    }

    /**
     * Send password reset link
     *
     * @return JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        try {
            $result = $this->authService->forgotPassword($request->only(['email']));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset password
     *
     * @return JsonResponse
     */
    public function resetPassword(Request $request)
    {
        try {
            $result = $this->authService->resetPassword($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify email
     *
     * @return JsonResponse
     */
    public function verifyEmail(Request $request, string $id, string $hash)
    {
        try {
            $result = $this->authService->verifyEmail(['id' => $id, 'hash' => $hash]);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Email verified successfully',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resend verification email
     *
     * @return JsonResponse
     */
    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // Only send to an existing, still-unverified account. The response is
        // identical either way so this endpoint cannot be used to enumerate
        // registered email addresses.
        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $this->authService->resendVerification($user);
            } catch (\Exception $e) {
                Log::warning('Failed to resend verification email', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email address, a verification link has been sent.',
        ]);
    }
}
