<?php

use App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\Billing\AdminBillingController;
use App\Http\Controllers\Api\V1\Admin\Billing\AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\Billing\PaymentRefundController;
use App\Http\Controllers\Api\V1\Admin\Billing\PlanProviderPriceController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\AI\AIConfigurationController;
use App\Http\Controllers\Api\V1\AI\AIStreamController;
use App\Http\Controllers\Api\V1\AI\AIUsageController;
use App\Http\Controllers\Api\V1\Analytics\AgentAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\AIAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsExportController;
use App\Http\Controllers\Api\V1\Analytics\ConversationAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\CustomerAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\KnowledgeBaseAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\OverviewController;
use App\Http\Controllers\Api\V1\Analytics\TicketAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\WidgetAnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Billing\PaymentController;
use App\Http\Controllers\Api\V1\Billing\PlanController;
use App\Http\Controllers\Api\V1\Billing\ProviderController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Billing\WebhookController;
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
use App\Models\Api\V1\NotificationController;
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

// Route::prefix('v1/widget')->group(function () {
//     Route::post('/bootstrap', [WidgetController::class, 'bootstrap']);
//     Route::post('/session', [WidgetController::class, 'session']);
//     Route::post('/messages', [WidgetController::class, 'sendMessage']);
//     Route::get('/messages', [WidgetController::class, 'getMessages']);
//     Route::get('/conversation', [WidgetController::class, 'getConversation']);
// });

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

// Public Plan Routes
Route::prefix('v1/plans')->group(function () {
    Route::get('/', [PlanController::class, 'index']);
    Route::get('/compare', [PlanController::class, 'compare']);
    Route::get('/{id}', [PlanController::class, 'show']);
});

// todo; Webhooks
Route::prefix('v1/billing/webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'handle'])
        ->defaults('provider', 'stripe');
    Route::post('/paypal', [WebhookController::class, 'handle'])
        ->defaults('provider', 'paypal');
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

     Route::middleware(['jwt.auth'])->group(function () {
        // Auth Routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        });
     });

    // Protected Routes - Authentication Required
    Route::middleware(['jwt.auth', 'tenant.aware', 'set.permission.team'])->group(function () {

        // Tenant Routes
        Route::prefix('tenants')->group(function () {
            Route::get ('/current',            [TenantController::class, 'current']);
            Route::get ('/my-tenants',         [TenantController::class, 'getUserTenants']);
            Route::post('/',                   [TenantController::class, 'store']);
            Route::put ('/{id}',               [TenantController::class, 'update']);
            Route::post('/switch/{tenantId}',  [TenantController::class, 'switchTenant']);
            Route::get ('/{tenantId}/users',   [TenantController::class, 'getUsers']);
            Route::post('/{tenantId}/invite',  [TenantController::class, 'inviteUser'])
                ->middleware('permission:team.invite');
            Route::delete('/{tenantId}/users/{userId}', [TenantController::class, 'removeUser'])
                ->middleware('permission:team.remove');
            Route::put ('/{tenantId}/users/{userId}/role', [TenantController::class, 'updateUserRole'])
                ->middleware('permission:team.assign_role');
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
            Route::get ('/',       [CustomerController::class, 'index'])  ->middleware('permission:customers.view');
            Route::post('/',       [CustomerController::class, 'store'])  ->middleware('permission:customers.create');
            Route::get('/statistics', [CustomerController::class, 'statistics']);
            Route::get('/tags', [CustomerController::class, 'tags']);
            Route::get('/export', [CustomerController::class, 'export']);
            Route::get ('/{id}',   [CustomerController::class, 'show'])   ->middleware('permission:customers.view');
            Route::put ('/{id}',   [CustomerController::class, 'update']) ->middleware('permission:customers.update');
            Route::delete('/{id}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

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
            Route::get('/configuration', [AIConfigurationController::class, 'show'])
                ->middleware('ai.rate.limit:60,60');
            Route::put('/configuration', [AIConfigurationController::class, 'update'])
                ->middleware('ai.rate.limit:20,60');
            Route::post('/test', [AIConfigurationController::class, 'test'])
                ->middleware('ai.rate.limit:10,60');

            // AI Streaming
            // Route::get('/ai/stream/{conversation}/{message}', [AIStreamController::class, 'stream']);

            // Streaming
            Route::get('/stream/{conversation}/{message}', [AIStreamController::class, 'stream'])
                ->middleware('ai.rate.limit:10,60');

            // Usage & Analytics
            Route::get('/usage', [AIUsageController::class, 'index'])
                ->middleware('ai.rate.limit:60,60');
            Route::get('/health', [AIUsageController::class, 'health'])
                ->middleware('ai.rate.limit:60,60');
            Route::get('/analytics', [AIUsageController::class, 'analytics'])
                ->middleware('ai.rate.limit:60,60');
            Route::get('/logs', [AIUsageController::class, 'logs'])
                ->middleware('ai.rate.limit:60,60');
        });

        // todo; Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/overview',      [OverviewController::class, 'index'])              ->middleware('permission:analytics.view');
            Route::get('/conversations', [ConversationAnalyticsController::class, 'index'])->middleware('permission:analytics.conversations');
            Route::get('/customers',     [CustomerAnalyticsController::class, 'index'])    ->middleware('permission:analytics.customers');
            Route::get('/agents', [AgentAnalyticsController::class, 'index']);
            Route::get('/tickets', [TicketAnalyticsController::class, 'index']);
            Route::get('/ai', [AIAnalyticsController::class, 'index']);
            Route::get('/widget', [WidgetAnalyticsController::class, 'index']);
            Route::get('/knowledge-base', [KnowledgeBaseAnalyticsController::class, 'index']);

            // todo; Exports
            Route::prefix('exports')->group(function () {
                Route::get('/', [AnalyticsExportController::class, 'index']);
                Route::post('/', [AnalyticsExportController::class, 'store']);
                Route::get('/{export}/download', [AnalyticsExportController::class, 'download'])
                    ->name('api.v1.analytics.exports.download');

                Route::get('/download-now', [AnalyticsExportController::class, 'downloadNow']);
            });

        });

        // todo; SUPER ADMIN (no tenant context)
        Route::middleware(['jwt.auth'])->prefix('admin')->group(function () {
            Route::prefix('analytics')->group(function () {
                Route::get('/overview', [AdminAnalyticsController::class, 'overview']);
                Route::get('/tenant-usage', [AdminAnalyticsController::class, 'tenantUsage']);
            });
        });

        // todo; Notification route
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        });

        //todo; Subscription Routes
        Route::prefix('subscription')->group(function () {
            Route::get('/current', [SubscriptionController::class, 'current']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::post('/upgrade', [SubscriptionController::class, 'upgrade']);
            Route::post('/downgrade', [SubscriptionController::class, 'downgrade']);
            Route::post('/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('/resume', [SubscriptionController::class, 'resume']);
            Route::post('/validate-coupon', [SubscriptionController::class, 'validateCoupon']);

            // Checkout
            Route::post('/checkout', [SubscriptionController::class, 'checkout']);
            Route::post('/checkout/verify', [SubscriptionController::class, 'verify']);
            Route::get('/providers', [ProviderController::class, 'index']);
        });

        //todo; Invoice Routes
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/statistics', [InvoiceController::class, 'statistics']);
            Route::get('/{id}', [InvoiceController::class, 'show']);
            Route::get('/{id}/download', [InvoiceController::class, 'download']);
        });

        //todo; Payment Routes
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index']);
            Route::get('/statistics', [PaymentController::class, 'statistics']);
        });

        // Usage (for frontend)
        // Route::get('/billing/usage', function () {
        //     $tracker = app(\App\Services\Billing\UsageTracker::class);
        //     $tenant = app('current_tenant');

        //     return response()->json([
        //         'success' => true,
        //         'data' => $tracker->getSummary($tenant->id),
        //     ]);
        // });

        // Usage (for frontend) — must never return 402
        Route::get('/billing/usage', function () {
            $tenant = app('current_tenant');

            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenant context.',
                ], 400);
            }

            // Check for an active subscription first
            $subscription = app(\App\Services\Billing\SubscriptionService::class)
                ->getActiveSubscription($tenant->id);

            // No subscription → return zeroed-out usage, HTTP 200
            if (! $subscription) {
                $empty = ['used' => 0, 'limit' => 0, 'percentage' => 0, 'remaining' => 0];

                return response()->json([
                    'success'         => true,
                    'no_subscription' => true,
                    'data'            => [
                        'ai'            => $empty,
                        'agents'        => $empty,
                        'customers'     => $empty,
                        'widgets'       => $empty,
                        'documents'     => $empty,
                        'kb_articles'   => $empty,
                        'conversations' => $empty,
                        'storage'       => $empty,
                    ],
                ]);
            }

            $tracker = app(\App\Services\Billing\UsageTracker::class);

            return response()->json([
                'success' => true,
                'data'    => $tracker->getSummary($tenant->id),
            ]);
        });

    });
});

Route::prefix('v1/admin/billing')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        // Plans
        Route::get('/plans', [AdminBillingController::class, 'plans']);
        Route::post('/plans', [AdminBillingController::class, 'storePlan']);
        Route::put('/plans/{plan}', [AdminBillingController::class, 'updatePlan']);
        Route::delete('/plans/{plan}', [AdminBillingController::class, 'destroyPlan']);
        Route::get   ('/plans/{plan}/prices',[PlanProviderPriceController::class, 'index']);
        Route::post  ('/plans/{plan}/prices',[PlanProviderPriceController::class, 'store']);
        Route::delete('/prices/{price}',[PlanProviderPriceController::class, 'destroy']);

        // Subscriptions
        Route::get('/subscriptions', [AdminBillingController::class, 'subscriptions']);
        Route::post('/subscriptions/{id}/cancel', [AdminBillingController::class, 'cancelSubscription']);
        Route::post('/subscriptions/{id}/extend', [AdminBillingController::class, 'extendSubscription']);

        // Invoices
        Route::get('/invoices', [AdminBillingController::class, 'invoices']);

        // Payments
        Route::get('/payments', [AdminBillingController::class, 'payments']);
        Route::post('/payments/{payment}/refund', [PaymentRefundController::class, 'refund']);

        // Analytics
        Route::get('/analytics', [AdminBillingController::class, 'analytics']);
        Route::get('/tenants/{tenant}', [AdminBillingController::class, 'tenantSummary']);

        // Coupons
        Route::get('/coupons', [AdminCouponController::class, 'index']);
        Route::post('/coupons', [AdminCouponController::class, 'store']);
        Route::get('/coupons/{coupon}', [AdminCouponController::class, 'show']);
        Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy']);
    });


Route::prefix('v1/admin/rbac')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {

        // Roles
        Route::get   ('/roles',                     [RoleController::class, 'index']);
        Route::post  ('/roles',                     [RoleController::class, 'store']);
        Route::get   ('/roles/{role}',              [RoleController::class, 'show']);
        Route::put   ('/roles/{role}',              [RoleController::class, 'update']);
        Route::delete('/roles/{role}',              [RoleController::class, 'destroy']);
        Route::put   ('/roles/{role}/permissions',  [RoleController::class, 'syncPermissions']);

        // Permissions
        Route::get   ('/permissions',               [PermissionController::class, 'index']);
        Route::post  ('/permissions',               [PermissionController::class, 'store']);
        Route::put   ('/permissions/{permission}',  [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}',  [PermissionController::class, 'destroy']);

        // User ↔ Role
        Route::post  ('/users/{user}/role',         [UserRoleController::class, 'assign']);
        Route::delete('/users/{user}/role',         [UserRoleController::class, 'revoke']);
    });

/*
|--------------------------------------------------------------------------
| RBAC — Tenant Owner / Admin (scoped to their tenant)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/rbac')
    ->middleware(['jwt.auth', 'tenant.aware', 'set.permission.team'])
    ->group(function () {

        // Roles — tenant can view global roles + manage their own
        Route::get   ('/roles',                     [RoleController::class, 'index'])
            ->middleware('permission:team.view');
        Route::post  ('/roles',                     [RoleController::class, 'store'])
            ->middleware('permission:team.assign_role');
        Route::get   ('/roles/{role}',              [RoleController::class, 'show'])
            ->middleware('permission:team.view');
        Route::put   ('/roles/{role}',              [RoleController::class, 'update'])
            ->middleware('permission:team.assign_role');
        Route::delete('/roles/{role}',              [RoleController::class, 'destroy'])
            ->middleware('permission:team.assign_role');
        Route::put   ('/roles/{role}/permissions',  [RoleController::class, 'syncPermissions'])
            ->middleware('permission:team.assign_role');

        // Permissions — read-only for tenant users
        Route::get   ('/permissions',               [PermissionController::class, 'index'])
            ->middleware('permission:team.view');

        // User ↔ Role
        Route::post  ('/users/{user}/role',         [UserRoleController::class, 'assign'])
            ->middleware('permission:team.assign_role');
        Route::delete('/users/{user}/role',         [UserRoleController::class, 'revoke'])
            ->middleware('permission:team.assign_role');
    });