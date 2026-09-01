<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Team\InvitationController;
use App\Http\Controllers\Api\V1\Team\TeamMemberController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public Routes - No Authentication Required
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('/verification.verify');
        Route::post('/validate-token', [AuthController::class, 'validateToken']);
    });

    // Protected Routes - Authentication Required
    Route::middleware(['jwt.auth', 'tenant.aware'])->group(function () {

        // Auth Routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        });

        // Tenant Routes
        Route::prefix('tenants')->group(function () {
            Route::get('/current', [TenantController::class, 'current']);
            Route::get('/my-tenants', [TenantController::class, 'getUserTenants']);
            Route::post('/', [TenantController::class, 'store']);
            Route::put('/{id}', [TenantController::class, 'update']);
            Route::post('/switch/{tenantId}', [TenantController::class, 'switchTenant']);
            Route::get('/{tenantId}/users', [TenantController::class, 'getUsers']);
            Route::post('/{tenantId}/invite', [TenantController::class, 'inviteUser']);
            Route::delete('/{tenantId}/users/{userId}', [TenantController::class, 'removeUser']);
            Route::put('/{tenantId}/users/{userId}/role', [TenantController::class, 'updateUserRole']);
        });

        // todo; ======================  WORKSPACE ROUTES =======================

        Route::prefix('workspace')->group(function () {
            // Basic CRUD
            Route::get('/', [WorkspaceController::class, 'show']);
            Route::put('/', [WorkspaceController::class, 'update']);

            // Logo Management
            Route::post('/logo', [WorkspaceController::class, 'updateLogo']);
            Route::delete('/logo', [WorkspaceController::class, 'deleteLogo']);

            // Favicon Management
            Route::post('/favicon', [WorkspaceController::class, 'updateFavicon']);
            Route::delete('/favicon', [WorkspaceController::class, 'deleteFavicon']);

            // Business Hours
            Route::put('/business-hours', [WorkspaceController::class, 'updateBusinessHours']);

            // Statistics
            Route::get('/statistics', [WorkspaceController::class, 'statistics']);
        });

        // todo; ===================== TEAM MANAGEMENT ROUTES =====================
        Route::prefix('team')->group(function () {

            // Members
            Route::prefix('members')->group(function () {
                Route::get('/', [TeamMemberController::class, 'index']);
                Route::get('/statistics', [TeamMemberController::class, 'statistics']);
                Route::get('/departments', [TeamMemberController::class, 'departments']);
                Route::get('/{id}', [TeamMemberController::class, 'show']);
                Route::put('/{id}', [TeamMemberController::class, 'update']);
                Route::delete('/{id}', [TeamMemberController::class, 'destroy']);
            });

            // Invitations
            Route::prefix('invitations')->group(function () {
                Route::get('/', [InvitationController::class, 'index']);
                Route::post('/', [InvitationController::class, 'store']);
                Route::post('/{id}/resend', [InvitationController::class, 'resend']);
                Route::delete('/{id}', [InvitationController::class, 'destroy']);
            });

        });


        // Profile Routes
        // Route::prefix('profile')->group(function () {
        //     Route::get('/', [ProfileController::class, 'show']);
        //     Route::put('/', [ProfileController::class, 'update']);
        //     Route::put('avatar', [ProfileController::class, 'updateAvatar']);
        // });

        // Audit Log Routes
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/statistics', [AuditLogController::class, 'statistics']);
            Route::get('/users/{userId}', [AuditLogController::class, 'getUserLogs']);
        });

        // Test Route - Can be removed later
        Route::get('test', function () {
            $user = auth()->user();
            $tenant = app('current_tenant');

            return response()->json([
                'message' => 'Authenticated successfully',
                'user' => $user->only(['id', 'email', 'full_name']),
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                ] : null,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
            ]);
        });

        Route::get('/test-audit-log', function () {
            try {
                $auditLogService = app(\App\Services\AuditLogService::class);

                $result = $auditLogService->log(
                    'test_log',
                    'test',
                    1,
                    ['old' => 'value'],
                    ['new' => 'value'],
                    ['test' => 'metadata']
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Audit log created',
                    'data' => $result,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ], 500);
            }
        });

        Route::get('/debug/tenant', function (Request $request) {
            return response()->json([
                'user' => auth()->user()?->id,
                'tenant_from_attributes' => $request->attributes->get('current_tenant')?->id,
                'user_current_tenant' => auth()->user()?->current_tenant_id,
                'header_tenant' => $request->header('X-Tenant-ID'),
                'user_tenants' => auth()->user()?->tenants()->pluck('id')->toArray(),
                'headers' => $request->headers->all(),
            ]);
        });

        Route::get('/debug/members', function (Request $request) {
            try {
                $members = \App\Models\TenantUser::with('user')->limit(5)->get();
                return response()->json([
                    'success' => true,
                    'count' => $members->count(),
                    'data' => $members->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'user_name' => $m->user?->full_name,
                            'email' => $m->user?->email,
                            'role' => $m->role,
                        ];
                    }),
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

    });
});

Route::post('/v1/team/invitations/accept/{token}', [InvitationController::class, 'accept']);
