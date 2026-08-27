<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\AuthService;
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
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {

        // dd($request);

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
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Login successful! Welcome back, ' . $result['user']['first_name'],
                'data' => $result,
            ]);
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Logout failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh JWT token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Token refresh failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user details
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Could not fetch user details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change user password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Password change failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate token validity
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateToken(Request $request)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
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
                'message' => 'Token validation failed: ' . $e->getMessage(),
                'valid' => false,
            ], 500);
        }
    }

    /**
     * Send password reset link
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        try {
            $result = $this->authService->forgotPassword($request->only(['email']));

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email',
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
                'message' => 'Failed to send reset link: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Password reset failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        try {
            $result = $this->authService->verifyEmail($request->only(['id', 'hash']));

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend verification email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerification(Request $request)
    {
        try {
            $user = auth()->user();
            $result = $this->authService->resendVerification($user);

            return response()->json([
                'success' => true,
                'message' => 'Verification email resent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification: ' . $e->getMessage(),
            ], 500);
        }
    }

}
