<?php

use App\Http\Controllers\Api\V1\AI\AIConfigurationController;
use App\Http\Controllers\Api\V1\AI\AIStreamController;
use App\Http\Controllers\Api\V1\AI\AIUsageController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatWidget\ChatWidgetController;
use App\Http\Controllers\Api\V1\ChatWidget\WidgetStatisticsController;
use App\Http\Controllers\Api\V1\Conversation\ConversationController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\KnowledgeBase\ArticleController;
use App\Http\Controllers\Api\V1\KnowledgeBase\CategoryController;
use App\Http\Controllers\Api\V1\Message\MessageController;
use App\Http\Controllers\Api\V1\Team\InvitationController;
use App\Http\Controllers\Api\V1\Team\TeamMemberController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\Ticket\TicketCommentController;
use App\Http\Controllers\Api\V1\Ticket\TicketController;
use App\Http\Controllers\Api\V1\Widget\WidgetController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Models\TenantUser;
use App\Services\AuditLogService;
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

Route::post('/v1/team/invitations/accept/{token}', [InvitationController::class, 'accept']);

Route::prefix('v1/widget')->group(function () {
    Route::post('/bootstrap', [WidgetController::class, 'bootstrap']);
    Route::post('/session', [WidgetController::class, 'session']);
    Route::post('/messages', [WidgetController::class, 'sendMessage']);
    Route::get('/messages', [WidgetController::class, 'getMessages']);
    Route::get('/conversation', [WidgetController::class, 'getConversation']);
});

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

        // todo; Message Routes - Nested under conversations
        Route::prefix('conversations/{conversation}')->group(function () {

            Route::get('/messages', [MessageController::class, 'index']);
            Route::post('/messages', [MessageController::class, 'store']);
            Route::post('/notes', [MessageController::class, 'addNote']);
        });

        // todo; Standalone message routes
        Route::prefix('messages')->group(function () {
            Route::get('/{id}', [MessageController::class, 'show']);
            Route::delete('/{id}', [MessageController::class, 'destroy']);
        });

        // todo; Chat Widgets
        Route::prefix('chat-widgets')->group(function () {
            Route::get('/', [ChatWidgetController::class, 'index']);
            Route::post('/', [ChatWidgetController::class, 'store']);
            Route::get('/{chatWidget}', [ChatWidgetController::class, 'show']);
            Route::put('/{chatWidget}', [ChatWidgetController::class, 'update']);
            Route::delete('/{chatWidget}', [ChatWidgetController::class, 'destroy']);

            // Actions
            Route::post('/{chatWidget}/enable', [ChatWidgetController::class, 'enable']);
            Route::post('/{chatWidget}/disable', [ChatWidgetController::class, 'disable']);
            Route::post('/{chatWidget}/regenerate-key', [ChatWidgetController::class, 'regenerateKey']);
            Route::get('/{chatWidget}/installation-code', [ChatWidgetController::class, 'installationCode']);

            // Statistics
            Route::get('/{chatWidget}/statistics', [WidgetStatisticsController::class, 'show']);
            Route::get('/{chatWidget}/analytics', [WidgetStatisticsController::class, 'analytics']);
        });

        // todo; Ticket Routes
        Route::prefix('tickets')->group(function () {
            Route::get('/', [TicketController::class, 'index']);
            Route::post('/', [TicketController::class, 'store']);
            Route::get('/statistics', [TicketController::class, 'statistics']);
            Route::get('/{id}', [TicketController::class, 'show']);
            Route::put('/{id}', [TicketController::class, 'update']);
            Route::delete('/{id}', [TicketController::class, 'destroy']);

            // Status Actions
            Route::post('/{id}/assign', [TicketController::class, 'assign']);
            Route::post('/{id}/unassign', [TicketController::class, 'unassign']);
            Route::post('/{id}/start', [TicketController::class, 'start']);
            Route::post('/{id}/pending', [TicketController::class, 'pending']);
            Route::post('/{id}/resolve', [TicketController::class, 'resolve']);
            Route::post('/{id}/reopen', [TicketController::class, 'reopen']);
            Route::post('/{id}/close', [TicketController::class, 'close']);

            // Comment Routes
            Route::get('/{ticket}/comments', [TicketCommentController::class, 'index']);
            Route::post('/{ticket}/comments', [TicketCommentController::class, 'store']);
            Route::delete('/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);
        });

        //todo; Knowledge Base Routes
        Route::prefix('knowledge-base')->group(function () {

            //todo; Categories
            Route::prefix('categories')->group(function () {
                Route::get('/', [CategoryController::class, 'index']);
                Route::get('/all', [CategoryController::class, 'all']);
                Route::post('/', [CategoryController::class, 'store']);
                Route::get('/{id}', [CategoryController::class, 'show']);
                Route::put('/{id}', [CategoryController::class, 'update']);
                Route::delete('/{id}', [CategoryController::class, 'destroy']);
                Route::post('/{id}/activate', [CategoryController::class, 'activate']);
                Route::post('/{id}/deactivate', [CategoryController::class, 'deactivate']);
            });

            //todo; Articles
            Route::prefix('articles')->group(function () {
                Route::get('/', [ArticleController::class, 'index']);
                Route::get('/statistics', [ArticleController::class, 'statistics']);
                Route::get('/search/ai', [ArticleController::class, 'searchForAI']);
                Route::post('/', [ArticleController::class, 'store']);
                Route::get('/{id}', [ArticleController::class, 'show']);
                Route::put('/{id}', [ArticleController::class, 'update']);
                Route::delete('/{id}', [ArticleController::class, 'destroy']);
                Route::post('/{id}/publish', [ArticleController::class, 'publish']);
                Route::post('/{id}/unpublish', [ArticleController::class, 'unpublish']);
                Route::post('/{id}/archive', [ArticleController::class, 'archive']);
            });

        });

        // todo; AI Routes
        Route::prefix('ai')->group(function () {
            Route::get('/configuration', [AIConfigurationController::class, 'show']);
            Route::put('/configuration', [AIConfigurationController::class, 'update']);
            Route::post('/test', [AIConfigurationController::class, 'test']);

            // AI Streaming
            // Route::get('/ai/stream/{conversation}/{message}', [AIStreamController::class, 'stream']);

            // Streaming
            Route::get('/stream/{conversation}/{message}', [AIStreamController::class, 'stream']);

            // Usage & Analytics
            Route::get('/usage', [AIUsageController::class, 'index']);
            Route::get('/health', [AIUsageController::class, 'health']);
            Route::get('/analytics', [AIUsageController::class, 'analytics']);
            Route::get('/logs', [AIUsageController::class, 'logs']);
        });

        // AI Check
        Route::get('/debug/ai-check', function () {
            try {
                $tenant = app('current_tenant');
                $config = \App\Models\Tenant\AIConfiguration::first();
                $gemini = app(\App\Ai\Services\GeminiService::class);

                return response()->json([
                    'tenant' => $tenant ? [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                    ] : null,
                    'ai_config' => $config,
                    'ai_enabled' => $config?->enabled ?? false,
                    'auto_reply_enabled' => $config?->auto_reply_enabled ?? false,
                    'streaming_enabled' => $config?->streaming_enabled ?? false,
                    'provider' => $config?->provider ?? 'gemini',
                    'model' => $config?->model ?? 'gemini-1.5-flash',
                    'gemini_configured' => $gemini->isConfigured(),
                    'tools_available' => [
                        'search_knowledge_base',
                        'get_customer',
                        'get_conversation',
                        'create_ticket',
                        'escalate_conversation',
                        'rag_search',
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
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

Route::prefix('v1/widget')->group(function () {
    Route::post('/bootstrap', [WidgetController::class, 'bootstrap'])
        ->middleware('widget.rate.limit:30,60');

    Route::post('/session', [WidgetController::class, 'session'])
        ->middleware('widget.rate.limit:20,60');

    Route::post('/messages', [WidgetController::class, 'sendMessage'])
        ->middleware('widget.rate.limit:20,60');

    Route::get('/messages', [WidgetController::class, 'getMessages'])
        ->middleware('widget.rate.limit:30,60');

    Route::get('/conversation', [WidgetController::class, 'getConversation'])
        ->middleware('widget.rate.limit:30,60');
});