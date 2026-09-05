<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Conversation\ConversationController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
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


            Route::prefix('members')->group(function () {
                Route::get('/', [TeamMemberController::class, 'index']);
                Route::get('/statistics', [TeamMemberController::class, 'statistics']);
                Route::get('/departments', [TeamMemberController::class, 'departments']);

                Route::get('/tenants', [TeamMemberController::class, 'tenants']); // Super Admin only
                // Members

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

        //todo; =========== Customer Management Routes ==========
        Route::prefix('customers')->group(function () {
            // Main CRUD
            Route::get('/', [CustomerController::class, 'index']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/statistics', [CustomerController::class, 'statistics']);
            Route::get('/tags', [CustomerController::class, 'tags']);
            Route::get('/export', [CustomerController::class, 'export']);
            Route::get('/{id}', [CustomerController::class, 'show']);
            Route::put('/{id}', [CustomerController::class, 'update']);
            Route::delete('/{id}', [CustomerController::class, 'destroy']);

            // Restore (soft delete)
            Route::post('/{id}/restore', [CustomerController::class, 'restore']);
            Route::delete('/{id}/force', [CustomerController::class, 'forceDelete']);

            // Block/Unblock
            Route::post('/{id}/block', [CustomerController::class, 'block']);
            Route::post('/{id}/unblock', [CustomerController::class, 'unblock']);

            // Tags
            Route::post('/{id}/tags', [CustomerController::class, 'addTag']);
            Route::delete('/{id}/tags/{tag}', [CustomerController::class, 'removeTag']);

            // Bulk Operations
            Route::post('/bulk-delete', [CustomerController::class, 'bulkDelete']);
        });

        // todo; ========== Conversation Routes ===========
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ConversationController::class, 'index']);
            Route::post('/', [ConversationController::class, 'store']);
            Route::get('/statistics', [ConversationController::class, 'statistics']);
            Route::get('/{id}', [ConversationController::class, 'show']);
            Route::put('/{id}', [ConversationController::class, 'update']);
            Route::delete('/{id}', [ConversationController::class, 'destroy']);

            // Status Actions
            Route::post('/{id}/resolve', [ConversationController::class, 'resolve']);
            Route::post('/{id}/reopen', [ConversationController::class, 'reopen']);
            Route::post('/{id}/close', [ConversationController::class, 'close']);

            // Assignment Actions
            Route::post('/{id}/assign', [ConversationController::class, 'assign']);
            Route::post('/{id}/unassign', [ConversationController::class, 'unassign']);
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