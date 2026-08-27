<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Register a new user with default tenant
     */
    public function register(array $data): array
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|timezone',
            'company_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Create user
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'uuid' => (string) Str::uuid(),
        ]);

        // Create default tenant
        $tenant = $this->tenantService->createDefaultTenant($user, $data);

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return [
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'is_active' => $user->is_active,
                'current_tenant_id' => $user->current_tenant_id,
            ],
            'tenant' => $tenant,
            'token' => $token,
        ];
    }

    /**
     * Login user
     */
    public function login(array $credentials): array
    {

        // Log::info('AuthService::login called', ['credentials' => array_keys($credentials)]);

        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.']
                // 'No account found with this email address.'
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'account' => ['Your account has been deactivated. Please contact support.']
                // 'Your account has been deactivated. Please contact support.'
            ]);
        }

        // Check email verification
        if (!$user->hasVerifiedEmail()) {
            // throw ValidationException::withMessages([
            // 'email' => ['Please verify your email address before logging in.']
            // ]);
            throw ValidationException::withMessages([
                'email' => ['Please verify your email address before logging in.']
                // 'Please verify your email address before logging in.'
            ]);
        }

        // Attempt login with JWT
        if (!$token = JWTAuth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'password' => ['The password you entered is incorrect.']
                // 'The password you entered is incorrect.'
            ]);
        }

        $user = auth()->user();
        // Load user permissions
        $user->load('roles.permissions');


        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        // Get current tenant
        $currentTenant = $user->currentTenant ?? $user->tenants()->first();

        return [
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'current_tenant_id' => $user->current_tenant_id,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
            ],
            'tenant' => $currentTenant ? [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
                'slug' => $currentTenant->slug,
                'subdomain' => $currentTenant->subdomain,
                'domain' => $currentTenant->domain,
                'logo_url' => $currentTenant->logo_url,
                'status' => $currentTenant->status,
            ] : null,
            'tenants' => $user->tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'subdomain' => $tenant->subdomain,
                    'domain' => $tenant->domain,
                    'logo_url' => $tenant->logo_url,
                    'status' => $tenant->status,
                    'pivot' => [
                        'role' => $tenant->pivot->role,
                        'department' => $tenant->pivot->department,
                    ]
                ];
            }),
            'token' => $token,
        ];
    }

    /**
     * Logout user
     */
    public function logout(): bool
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Refresh JWT token
     */
    public function refresh(): string
    {
        try {
            return JWTAuth::refresh(JWTAuth::getToken());
        } catch (TokenExpiredException $e) {
            throw new \Exception('Token expired. Please login again.');
        } catch (TokenInvalidException $e) {
            throw new \Exception('Token invalid. Please login again.');
        } catch (JWTException $e) {
            throw new \Exception('Could not refresh token: ' . $e->getMessage());
        }
    }

    /**
     * Get current user details
     */
    public function me(): array
    {
        $user = auth()->user();
        $currentTenant = $user->currentTenant;

        return [
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'current_tenant_id' => $user->current_tenant_id,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
            ],
            'tenant' => $currentTenant ? [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
                'slug' => $currentTenant->slug,
                'subdomain' => $currentTenant->subdomain,
                'domain' => $currentTenant->domain,
                'logo_url' => $currentTenant->logo_url,
                'status' => $currentTenant->status,
                'settings' => $currentTenant->settings,
            ] : null,
            'tenants' => $user->tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'subdomain' => $tenant->subdomain,
                    'domain' => $tenant->domain,
                    'logo_url' => $tenant->logo_url,
                    'status' => $tenant->status,
                    'pivot' => [
                        'role' => $tenant->pivot->role,
                        'department' => $tenant->pivot->department,
                    ]
                ];
            }),
        ];
    }

    /**
     * Change user password
     */
    public function changePassword(User $user, array $data): string
    {
        $validator = Validator::make($data, [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if (!Hash::check($data['current_password'], $user->password)) {
            throw new ValidationException(
                Validator::make([], ['current_password' => 'Current password is incorrect'])
            );
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        // Invalidate all tokens except current one
        JWTAuth::invalidate(JWTAuth::getToken());

        // Generate new token
        return JWTAuth::fromUser($user);
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): array
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $user = JWTAuth::authenticate($token);

            if (!$user) {
                return [
                    'valid' => false,
                    'message' => 'User not found',
                ];
            }

            return [
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                ],
                'expires_at' => $payload->get('exp'),
            ];
        } catch (TokenExpiredException $e) {
            return [
                'valid' => false,
                'message' => 'Token expired',
                'error' => 'token_expired',
            ];
        } catch (TokenInvalidException $e) {
            return [
                'valid' => false,
                'message' => 'Token invalid',
                'error' => 'token_invalid',
            ];
        } catch (JWTException $e) {
            return [
                'valid' => false,
                'message' => 'Token error: ' . $e->getMessage(),
                'error' => 'token_error',
            ];
        }
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(array $data): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email|exists:users',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = User::where('email', $data['email'])->first();

        // Generate reset token
        $token = Password::createToken($user);

        // Send email (implement your email logic)
        // Mail::to($user->email)->send(new PasswordResetMail($token, $user));

        return [
            'message' => 'Password reset link sent',
            'token' => $token, // In production, don't return this
        ];
    }

    /**
     * Reset password using token
     */
    public function resetPassword(array $data): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email|exists:users',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new \Exception('Invalid reset token or email');
        }

        // Generate new token for auto-login
        $user = User::where('email', $data['email'])->first();
        $token = JWTAuth::fromUser($user);

        return [
            'message' => 'Password reset successful',
            'token' => $token,
        ];
    }

    /**
     * Verify email
     */
    public function verifyEmail(array $data): array
    {
        $validator = Validator::make($data, [
            'id' => 'required|exists:users',
            'hash' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = User::find($data['id']);

        // Verify hash matches
        if (!hash_equals((string) $data['hash'], sha1($user->getEmailForVerification()))) {
            throw new \Exception('Invalid verification link');
        }

        if ($user->hasVerifiedEmail()) {
            throw new \Exception('Email already verified');
        }

        $user->markEmailAsVerified();

        return [
            'message' => 'Email verified successfully',
        ];
    }

    /**
     * Resend verification email
     */
    public function resendVerification(User $user): array
    {
        if ($user->hasVerifiedEmail()) {
            throw new \Exception('Email already verified');
        }

        // Send verification email
        // Mail::to($user->email)->send(new EmailVerificationMail($user));

        return [
            'message' => 'Verification email sent',
        ];
    }
}
