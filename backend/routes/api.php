<?php

use App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\Billing\AdminBillingController;
use App\Http\Controllers\Api\V1\Admin\Billing\AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\Billing\PaymentRefundController;
use App\Http\Controllers\Api\V1\Admin\Billing\PlanProviderPriceController;
use App\Http\Controllers\Api\V1\Admin\DemoRequestAdminController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\PlatformNotificationController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SystemHealthController;
use App\Http\Controllers\Api\V1\Admin\SystemSettingsController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\TenantLifecycleController as AdminTenantLifecycleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\UserLifecycleController;
use App\Http\Controllers\Api\V1\Admin\UserPermissionController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\Admin\UserSessionController;
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
use App\Http\Controllers\Api\V1\DemoRequestController;
use App\Http\Controllers\Api\V1\KnowledgeBase\ArticleController;
use App\Http\Controllers\Api\V1\KnowledgeBase\CategoryController;
use App\Http\Controllers\Api\V1\Message\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Team\InvitationController;
use App\Http\Controllers\Api\V1\Team\TeamMemberController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\Ticket\TicketCommentController;
use App\Http\Controllers\Api\V1\Ticket\TicketController;
use App\Http\Controllers\Api\V1\Widget\WidgetController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTracker;
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

Route::prefix('v1/widget')->middleware('maintenance.mode')->group(function () {
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
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware(['maintenance.mode:registration', 'throttle:auth', 'turnstile']);
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware(['throttle:auth', 'turnstile']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:password', 'turnstile']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:password');
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('/verification.verify');
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1');
        Route::post('/validate-token', [AuthController::class, 'validateToken'])
            ->middleware('throttle:auth');
    });

    Route::middleware(['jwt.auth'])->group(function () {
        // Auth Routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });

        // Profile Routes — available to every authenticated role
        // (super_admin, owner, admin, manager, support_agent)
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        });
    });

    // Protected Routes - Authentication Required
    Route::middleware(['jwt.auth', 'tenant.aware', 'set.permission.team', 'maintenance.mode', 'check.subscription'])->group(function () {

        // Tenant Routes
        Route::prefix('tenants')->group(function () {
            Route::get('/current', [TenantController::class, 'current']);
            Route::get('/my-tenants', [TenantController::class, 'getUserTenants']);
            Route::post('/', [TenantController::class, 'store']);
            Route::put('/{id}', [TenantController::class, 'update']);
            Route::post('/switch/{tenantId}', [TenantController::class, 'switchTenant']);
            Route::get('/{tenantId}/users', [TenantController::class, 'getUsers']);
            Route::post('/{tenantId}/invite', [TenantController::class, 'inviteUser'])
                ->middleware('permission:team.invite');
            Route::delete('/{tenantId}/users/{userId}', [TenantController::class, 'removeUser'])
                ->middleware('permission:team.remove');
            Route::put('/{tenantId}/users/{userId}/role', [TenantController::class, 'updateUserRole'])
                ->middleware('permission:team.assign_role');
        });

        // todo; ======================  WORKSPACE ROUTES =======================

        Route::prefix('workspace')->group(function () {
            // Basic CRUD
            Route::get('/', [WorkspaceController::class, 'show'])->middleware('permission:workspace.view');
            Route::put('/', [WorkspaceController::class, 'update'])->middleware('permission:workspace.update');

            // Logo Management
            Route::post('/logo', [WorkspaceController::class, 'updateLogo'])->middleware('permission:workspace.update');
            Route::delete('/logo', [WorkspaceController::class, 'deleteLogo'])->middleware('permission:workspace.update');

            // Favicon Management
            Route::post('/favicon', [WorkspaceController::class, 'updateFavicon'])->middleware('permission:workspace.update');
            Route::delete('/favicon', [WorkspaceController::class, 'deleteFavicon'])->middleware('permission:workspace.update');

            // Business Hours
            Route::put('/business-hours', [WorkspaceController::class, 'updateBusinessHours'])->middleware('permission:workspace.update');

            // Statistics
            Route::get('/statistics', [WorkspaceController::class, 'statistics'])->middleware('permission:workspace.view');
        });

        // todo; ===================== TEAM MANAGEMENT ROUTES =====================
        Route::prefix('team')->group(function () {

            Route::prefix('members')->group(function () {
                Route::get('/', [TeamMemberController::class, 'index'])->middleware('permission:team.view');
                Route::get('/statistics', [TeamMemberController::class, 'statistics'])->middleware('permission:team.view');
                Route::get('/departments', [TeamMemberController::class, 'departments'])->middleware('permission:team.view');

                Route::get('/tenants', [TeamMemberController::class, 'tenants'])->middleware('permission:team.view'); // Super Admin only
                // Members

                Route::get('/{id}', [TeamMemberController::class, 'show'])->middleware('permission:team.view');
                Route::put('/{id}', [TeamMemberController::class, 'update'])->middleware('permission:team.update');
                Route::post('/{id}/verify-email', [TeamMemberController::class, 'verifyEmail'])->middleware('permission:team.update');
                Route::delete('/{id}', [TeamMemberController::class, 'destroy'])->middleware('permission:team.remove');
            });

            // Invitations
            Route::prefix('invitations')->group(function () {
                Route::get('/', [InvitationController::class, 'index'])->middleware('permission:team.view');
                Route::post('/', [InvitationController::class, 'store'])->middleware('permission:team.invite');
                Route::post('/{id}/resend', [InvitationController::class, 'resend'])->middleware('permission:team.invite');
                Route::delete('/{id}', [InvitationController::class, 'destroy'])->middleware('permission:team.remove');
            });

        });

        // todo; =========== Customer Management Routes ==========
        Route::prefix('customers')->group(function () {
            // Main CRUD
            Route::get('/', [CustomerController::class, 'index'])->middleware('permission:customers.view');
            Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.create');
            Route::get('/statistics', [CustomerController::class, 'statistics'])->middleware('permission:customers.view');
            Route::get('/tags', [CustomerController::class, 'tags'])->middleware('permission:customers.view');
            Route::get('/export', [CustomerController::class, 'export'])->middleware('permission:customers.view');
            Route::get('/{id}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
            Route::put('/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
            Route::delete('/{id}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

            // Restore (soft delete)
            Route::post('/{id}/restore', [CustomerController::class, 'restore'])->middleware('permission:customers.delete');
            Route::delete('/{id}/force', [CustomerController::class, 'forceDelete'])->middleware('permission:customers.delete');

            // Block/Unblock
            Route::post('/{id}/block', [CustomerController::class, 'block'])->middleware('permission:customers.update');
            Route::post('/{id}/unblock', [CustomerController::class, 'unblock'])->middleware('permission:customers.update');

            // Tags
            Route::post('/{id}/tags', [CustomerController::class, 'addTag'])->middleware('permission:customers.update');
            Route::delete('/{id}/tags/{tag}', [CustomerController::class, 'removeTag'])->middleware('permission:customers.update');

            // Bulk Operations
            Route::post('/bulk-delete', [CustomerController::class, 'bulkDelete'])->middleware('permission:customers.delete');
        });

        // todo; ========== Conversation Routes ===========
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ConversationController::class, 'index'])->middleware('permission:conversations.view');
            Route::post('/', [ConversationController::class, 'store'])->middleware('permission:conversations.create');
            Route::get('/statistics', [ConversationController::class, 'statistics'])->middleware('permission:conversations.view');
            Route::get('/{id}', [ConversationController::class, 'show'])->middleware('permission:conversations.view');
            Route::put('/{id}', [ConversationController::class, 'update'])->middleware('permission:conversations.update');
            Route::delete('/{id}', [ConversationController::class, 'destroy'])->middleware('permission:conversations.delete');

            // Status Actions
            Route::post('/{id}/resolve', [ConversationController::class, 'resolve'])->middleware('permission:conversations.resolve');
            Route::post('/{id}/reopen', [ConversationController::class, 'reopen'])->middleware('permission:conversations.reopen');
            Route::post('/{id}/close', [ConversationController::class, 'close'])->middleware('permission:conversations.close');
            Route::post('/{id}/assign', [ConversationController::class, 'assign'])->middleware('permission:conversations.assign');
            Route::post('/{id}/unassign', [ConversationController::class, 'unassign'])->middleware('permission:conversations.unassign');
        });

        // todo; Message Routes - Nested under conversations
        Route::prefix('conversations/{conversation}')->group(function () {

            // Authorization is enforced in MessageService (conversations.* permissions);
            // message visibility is filtered there so plain members can still read public messages.
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
            Route::get('/', [ChatWidgetController::class, 'index'])->middleware('permission:widgets.view');
            Route::post('/', [ChatWidgetController::class, 'store'])->middleware('permission:widgets.create');
            Route::get('/{chatWidget}', [ChatWidgetController::class, 'show'])->middleware('permission:widgets.view');
            Route::put('/{chatWidget}', [ChatWidgetController::class, 'update'])->middleware('permission:widgets.update');
            Route::delete('/{chatWidget}', [ChatWidgetController::class, 'destroy'])->middleware('permission:widgets.delete');

            // Actions
            Route::post('/{chatWidget}/enable', [ChatWidgetController::class, 'enable'])->middleware('permission:widgets.enable');
            Route::post('/{chatWidget}/disable', [ChatWidgetController::class, 'disable'])->middleware('permission:widgets.disable');
            Route::post('/{chatWidget}/regenerate-key', [ChatWidgetController::class, 'regenerateKey'])->middleware('permission:widgets.regenerate_key');
            Route::get('/{chatWidget}/installation-code', [ChatWidgetController::class, 'installationCode'])->middleware('permission:widgets.view');

            // Statistics
            Route::get('/{chatWidget}/statistics', [WidgetStatisticsController::class, 'show'])->middleware('permission:widgets.view');
            Route::get('/{chatWidget}/analytics', [WidgetStatisticsController::class, 'analytics'])->middleware('permission:widgets.analytics');
        });

        // todo; Ticket Routes
        Route::prefix('tickets')->group(function () {
            Route::get('/', [TicketController::class, 'index'])->middleware('permission:tickets.view');
            Route::post('/', [TicketController::class, 'store'])->middleware('permission:tickets.create');
            Route::get('/statistics', [TicketController::class, 'statistics'])->middleware('permission:tickets.view');
            Route::get('/{id}', [TicketController::class, 'show'])->middleware('permission:tickets.view');
            Route::put('/{id}', [TicketController::class, 'update'])->middleware('permission:tickets.update');
            Route::delete('/{id}', [TicketController::class, 'destroy'])->middleware('permission:tickets.delete');

            // Status Actions
            Route::post('/{id}/assign', [TicketController::class, 'assign'])->middleware('permission:tickets.assign');
            Route::post('/{id}/unassign', [TicketController::class, 'unassign'])->middleware('permission:tickets.unassign');
            Route::post('/{id}/start', [TicketController::class, 'start'])->middleware('permission:tickets.update');
            Route::post('/{id}/pending', [TicketController::class, 'pending'])->middleware('permission:tickets.update');
            Route::post('/{id}/resolve', [TicketController::class, 'resolve'])->middleware('permission:tickets.resolve');
            Route::post('/{id}/reopen', [TicketController::class, 'reopen'])->middleware('permission:tickets.reopen');
            Route::post('/{id}/close', [TicketController::class, 'close'])->middleware('permission:tickets.close');

            // Comment Routes
            Route::get('/{ticket}/comments', [TicketCommentController::class, 'index']);
            Route::post('/{ticket}/comments', [TicketCommentController::class, 'store']);
            Route::delete('/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);
        });

        // todo; Knowledge Base Routes
        Route::prefix('knowledge-base')->group(function () {

            // todo; Categories
            Route::prefix('categories')->group(function () {
                Route::get('/', [CategoryController::class, 'index'])->middleware('permission:knowledge_base.view');
                Route::get('/all', [CategoryController::class, 'all'])->middleware('permission:knowledge_base.view');
                Route::post('/', [CategoryController::class, 'store'])->middleware('permission:knowledge_base.create');
                Route::get('/{id}', [CategoryController::class, 'show'])->middleware('permission:knowledge_base.view');
                Route::put('/{id}', [CategoryController::class, 'update'])->middleware('permission:knowledge_base.update');
                Route::delete('/{id}', [CategoryController::class, 'destroy'])->middleware('permission:knowledge_base.delete');
                Route::post('/{id}/activate', [CategoryController::class, 'activate'])->middleware('permission:knowledge_base.update');
                Route::post('/{id}/deactivate', [CategoryController::class, 'deactivate'])->middleware('permission:knowledge_base.update');
            });

            // todo; Articles
            Route::prefix('articles')->group(function () {
                Route::get('/', [ArticleController::class, 'index'])->middleware('permission:knowledge_base.view');
                Route::get('/statistics', [ArticleController::class, 'statistics'])->middleware('permission:knowledge_base.view');
                Route::get('/search/ai', [ArticleController::class, 'searchForAI'])->middleware('permission:knowledge_base.view');
                Route::post('/', [ArticleController::class, 'store'])->middleware('permission:knowledge_base.create');
                Route::get('/{id}', [ArticleController::class, 'show'])->middleware('permission:knowledge_base.view');
                Route::put('/{id}', [ArticleController::class, 'update'])->middleware('permission:knowledge_base.update');
                Route::delete('/{id}', [ArticleController::class, 'destroy'])->middleware('permission:knowledge_base.delete');
                Route::post('/{id}/publish', [ArticleController::class, 'publish'])->middleware('permission:knowledge_base.publish');
                Route::post('/{id}/unpublish', [ArticleController::class, 'unpublish'])->middleware('permission:knowledge_base.publish');
                Route::post('/{id}/archive', [ArticleController::class, 'archive'])->middleware('permission:knowledge_base.archive');
            });

        });

        // todo; AI Routes
        Route::prefix('ai')->group(function () {
            Route::get('/configuration', [AIConfigurationController::class, 'show'])
                ->middleware(['permission:ai.view', 'ai.rate.limit:60,60']);
            Route::put('/configuration', [AIConfigurationController::class, 'update'])
                ->middleware(['permission:ai.configure', 'ai.rate.limit:20,60']);
            Route::post('/test', [AIConfigurationController::class, 'test'])
                ->middleware(['permission:ai.configure', 'ai.rate.limit:10,60']);

            // AI Streaming
            // Route::get('/ai/stream/{conversation}/{message}', [AIStreamController::class, 'stream']);

            // Streaming
            Route::get('/stream/{conversation}/{message}', [AIStreamController::class, 'stream'])
                ->middleware(['permission:ai.use', 'ai.rate.limit:10,60']);

            // Usage & Analytics
            Route::get('/usage', [AIUsageController::class, 'index'])
                ->middleware(['permission:ai.view', 'ai.rate.limit:60,60']);
            Route::get('/health', [AIUsageController::class, 'health'])
                ->middleware(['permission:ai.view', 'ai.rate.limit:60,60']);
            Route::get('/analytics', [AIUsageController::class, 'analytics'])
                ->middleware(['permission:ai.analytics', 'ai.rate.limit:60,60']);
            Route::get('/logs', [AIUsageController::class, 'logs'])
                ->middleware(['permission:ai.view', 'ai.rate.limit:60,60']);
        });

        // todo; Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [OverviewController::class, 'index'])->middleware('permission:analytics.view');
            Route::get('/conversations', [ConversationAnalyticsController::class, 'index'])->middleware('permission:analytics.conversations');
            Route::get('/customers', [CustomerAnalyticsController::class, 'index'])->middleware('permission:analytics.customers');
            Route::get('/agents', [AgentAnalyticsController::class, 'index'])->middleware('permission:analytics.agents');
            Route::get('/tickets', [TicketAnalyticsController::class, 'index'])->middleware('permission:analytics.tickets');
            Route::get('/ai', [AIAnalyticsController::class, 'index'])->middleware('permission:analytics.ai');
            Route::get('/widget', [WidgetAnalyticsController::class, 'index'])->middleware('permission:analytics.widget');
            Route::get('/knowledge-base', [KnowledgeBaseAnalyticsController::class, 'index'])->middleware('permission:analytics.knowledge_base');

            // todo; Exports
            Route::prefix('exports')->group(function () {
                Route::get('/', [AnalyticsExportController::class, 'index'])->middleware('permission:analytics.export');
                Route::post('/', [AnalyticsExportController::class, 'store'])->middleware('permission:analytics.export');
                Route::get('/{export}/download', [AnalyticsExportController::class, 'download'])
                    ->middleware('permission:analytics.export')
                    ->name('api.v1.analytics.exports.download');

                Route::get('/download-now', [AnalyticsExportController::class, 'downloadNow'])->middleware('permission:analytics.export');
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

        // todo; Subscription Routes
        Route::prefix('subscription')->group(function () {
            Route::get('/current', [SubscriptionController::class, 'current'])->middleware('permission:billing.view');
            Route::post('/', [SubscriptionController::class, 'store'])->middleware('permission:billing.subscription');
            Route::post('/upgrade', [SubscriptionController::class, 'upgrade'])->middleware('permission:billing.subscription');
            Route::post('/downgrade', [SubscriptionController::class, 'downgrade'])->middleware('permission:billing.subscription');
            Route::post('/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:billing.subscription');
            Route::post('/resume', [SubscriptionController::class, 'resume'])->middleware('permission:billing.subscription');
            Route::post('/validate-coupon', [SubscriptionController::class, 'validateCoupon'])->middleware('permission:billing.subscription');

            // Checkout
            Route::post('/checkout', [SubscriptionController::class, 'checkout'])->middleware('permission:billing.subscription');
            Route::post('/checkout/verify', [SubscriptionController::class, 'verify'])->middleware('permission:billing.subscription');
            Route::get('/providers', [ProviderController::class, 'index'])->middleware('permission:billing.view');
        });

        // todo; Invoice Routes
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:billing.invoices');
            Route::get('/statistics', [InvoiceController::class, 'statistics'])->middleware('permission:billing.invoices');
            Route::get('/{id}', [InvoiceController::class, 'show'])->middleware('permission:billing.invoices');
            Route::get('/{id}/download', [InvoiceController::class, 'download'])->middleware('permission:billing.invoices');
        });

        // todo; Payment Routes
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->middleware('permission:billing.payments');
            Route::get('/statistics', [PaymentController::class, 'statistics'])->middleware('permission:billing.payments');
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
            $subscription = app(SubscriptionService::class)
                ->getActiveSubscription($tenant->id);

            // No subscription → return zeroed-out usage, HTTP 200
            if (! $subscription) {
                $empty = ['used' => 0, 'limit' => 0, 'percentage' => 0, 'remaining' => 0];

                return response()->json([
                    'success' => true,
                    'no_subscription' => true,
                    'data' => [
                        'ai' => $empty,
                        'agents' => $empty,
                        'customers' => $empty,
                        'widgets' => $empty,
                        'documents' => $empty,
                        'kb_articles' => $empty,
                        'conversations' => $empty,
                        'storage' => $empty,
                    ],
                ]);
            }

            $tracker = app(UsageTracker::class);

            return response()->json([
                'success' => true,
                'data' => $tracker->getSummary($tenant->id),
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
        Route::get('/plans/{plan}/prices', [PlanProviderPriceController::class, 'index']);
        Route::post('/plans/{plan}/prices', [PlanProviderPriceController::class, 'store']);
        Route::delete('/prices/{price}', [PlanProviderPriceController::class, 'destroy']);

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

Route::prefix('v1/admin/tenants')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        Route::get('/', [AdminTenantController::class, 'index'])
            ->middleware('permission:platform.tenants.view');
        Route::post('/', [AdminTenantController::class, 'store'])
            ->middleware('permission:platform.tenants.create');
        Route::get('/plans', [AdminTenantController::class, 'plans'])
            ->middleware('permission:platform.tenants.create');
        Route::get('/{tenant}', [AdminTenantController::class, 'show'])
            ->middleware('permission:platform.tenants.view');
        Route::put('/{tenant}', [AdminTenantController::class, 'update'])
            ->middleware('permission:platform.tenants.update');
        Route::get('/{tenant}/members', [AdminTenantController::class, 'members'])
            ->middleware('permission:platform.tenants.view_members');
        Route::get('/{tenant}/usage', [AdminTenantController::class, 'usage'])
            ->middleware('permission:platform.tenants.view_usage');
        Route::get('/{tenant}/activity', [AdminTenantController::class, 'activity'])
            ->middleware('permission:platform.tenants.view_activity');

        Route::post('/{tenant}/activate', [AdminTenantLifecycleController::class, 'activate'])
            ->middleware('permission:platform.tenants.activate');
        Route::post('/{tenant}/suspend', [AdminTenantLifecycleController::class, 'suspend'])
            ->middleware('permission:platform.tenants.suspend');
        Route::post('/{tenant}/archive', [AdminTenantLifecycleController::class, 'archive'])
            ->middleware('permission:platform.tenants.archive');
        Route::post('/{tenant}/restore', [AdminTenantLifecycleController::class, 'restore'])
            ->middleware('permission:platform.tenants.restore');
        Route::post('/{tenant}/retry-provisioning', [AdminTenantLifecycleController::class, 'retryProvisioning'])
            ->middleware('permission:platform.tenants.activate');
        Route::post('/{tenant}/transfer-owner', [AdminTenantLifecycleController::class, 'transferOwner'])
            ->middleware('permission:platform.tenants.transfer_owner');

        Route::post('/{tenant}/manage', [AdminTenantLifecycleController::class, 'manage'])
            ->middleware('permission:platform.tenants.manage');
        Route::post('/exit-context', [AdminTenantLifecycleController::class, 'exitContext'])
            ->middleware('permission:platform.tenants.manage');
    });

Route::prefix('v1/admin/users')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:platform.users.view');
        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:platform.users.create');
        Route::get('/stats', [UserController::class, 'stats'])
            ->middleware('permission:platform.users.view');
        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission:platform.users.view');
        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission:platform.users.update');
        Route::get('/{user}/memberships', [UserController::class, 'memberships'])
            ->middleware('permission:platform.users.view_memberships');
        Route::get('/{user}/activity', [UserController::class, 'activity'])
            ->middleware('permission:platform.users.view_activity');
        Route::post('/{user}/activate', [UserLifecycleController::class, 'activate'])
            ->middleware('permission:platform.users.activate');
        Route::post('/{user}/suspend', [UserLifecycleController::class, 'suspend'])
            ->middleware('permission:platform.users.suspend');
        Route::post('/{user}/revoke-sessions', [UserSessionController::class, 'revokeAll'])
            ->middleware('permission:platform.users.revoke_sessions');
        Route::post('/{user}/verify-email', [UserLifecycleController::class, 'verifyEmail'])
            ->middleware('permission:platform.users.verify_email');
    });

Route::prefix('v1/admin/rbac')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {

        // Roles
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);

        // User ↔ Role
        Route::post('/users/{user}/role', [UserRoleController::class, 'assign']);
        Route::delete('/users/{user}/role', [UserRoleController::class, 'revoke']);

        // User ↔ direct Permissions
        Route::put('/users/{user}/permissions', [UserPermissionController::class, 'sync']);
    });

/*
|--------------------------------------------------------------------------
| Platform — Audit Logs (Super Admin)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin/audit-logs')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        Route::get('/statistics', [AdminAuditLogController::class, 'statistics'])
            ->middleware('permission:platform.audit_logs');
        Route::get('/', [AdminAuditLogController::class, 'index'])
            ->middleware('permission:platform.audit_logs');
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
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:team.view');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:team.assign_role');
        Route::get('/roles/{role}', [RoleController::class, 'show'])
            ->middleware('permission:team.view');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:team.assign_role');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:team.assign_role');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
            ->middleware('permission:team.assign_role');

        // Permissions — read-only for tenant users
        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:team.view');

        // User ↔ Role
        Route::post('/users/{user}/role', [UserRoleController::class, 'assign'])
            ->middleware('permission:team.assign_role');
        Route::delete('/users/{user}/role', [UserRoleController::class, 'revoke'])
            ->middleware('permission:team.assign_role');
    });

/*
|--------------------------------------------------------------------------
| SYSTEM SETTINGS — Platform control plane (Super Admin only)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin/settings')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        Route::get('/', [SystemSettingsController::class, 'index'])
            ->middleware('permission:platform.settings.view');

        // General
        Route::get('/general', [SystemSettingsController::class, 'general'])
            ->middleware('permission:platform.settings.view');
        Route::put('/general', [SystemSettingsController::class, 'updateGeneral'])
            ->middleware('permission:platform.settings.general');

        // Security
        Route::get('/security', [SystemSettingsController::class, 'security'])
            ->middleware('permission:platform.settings.view');
        Route::put('/security', [SystemSettingsController::class, 'updateSecurity'])
            ->middleware('permission:platform.settings.security');

        // AI defaults
        Route::get('/ai', [SystemSettingsController::class, 'ai'])
            ->middleware('permission:platform.settings.view');
        Route::put('/ai', [SystemSettingsController::class, 'updateAi'])
            ->middleware('permission:platform.settings.ai');

        // Email
        Route::get('/email', [SystemSettingsController::class, 'email'])
            ->middleware('permission:platform.settings.view');
        Route::put('/email', [SystemSettingsController::class, 'updateEmail'])
            ->middleware('permission:platform.settings.email');
        Route::post('/email/test', [SystemSettingsController::class, 'testEmail'])
            ->middleware('permission:platform.settings.email');

        // Integrations
        Route::get('/integrations', [SystemSettingsController::class, 'integrations'])
            ->middleware('permission:platform.settings.view');
        Route::put('/integrations', [SystemSettingsController::class, 'updateIntegrations'])
            ->middleware('permission:platform.settings.integrations');
        Route::post('/integrations/{provider}/test', [SystemSettingsController::class, 'testIntegration'])
            ->middleware('permission:platform.settings.integrations');

        // Operations
        Route::get('/operations', [SystemSettingsController::class, 'operations'])
            ->middleware('permission:platform.settings.view');
        Route::put('/operations', [SystemSettingsController::class, 'updateOperations'])
            ->middleware('permission:platform.settings.operations');
        Route::get('/operations/health', [SystemSettingsController::class, 'health'])
            ->middleware('permission:platform.settings.view');
    });

/*
|--------------------------------------------------------------------------
| SYSTEM HEALTH — Platform operational monitoring (Super Admin only)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin/system-health')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        Route::get('/',
            [SystemHealthController::class, 'index'])
            ->middleware('permission:platform.system_health.view');

        Route::get('/checks',
            [SystemHealthController::class, 'checks'])
            ->middleware('permission:platform.system_health.view');

        Route::get('/checks/{check}',
            [SystemHealthController::class, 'showCheck'])
            ->middleware('permission:platform.system_health.view');

        Route::post('/checks/{check}/run',
            [SystemHealthController::class, 'runCheck'])
            ->middleware([
                'permission:platform.system_health.run',
                'throttle:'.config('health.on_demand.rate_limit', '10,1'),
            ]);

        Route::get('/metrics',
            [SystemHealthController::class, 'metrics'])
            ->middleware('permission:platform.system_health.view');

        Route::get('/incidents',
            [SystemHealthController::class, 'incidents'])
            ->middleware('permission:platform.system_health.view');

        Route::get('/incidents/{incident}',
            [SystemHealthController::class, 'showIncident'])
            ->middleware('permission:platform.system_health.view');

        Route::post('/incidents/{incident}/acknowledge',
            [SystemHealthController::class, 'acknowledgeIncident'])
            ->middleware('permission:platform.system_health.manage');

        Route::post('/incidents/{incident}/resolve',
            [SystemHealthController::class, 'resolveIncident'])
            ->middleware('permission:platform.system_health.manage');

        Route::get('/events',
            [SystemHealthController::class, 'events'])
            ->middleware('permission:platform.system_health.view');
    });

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS — Platform notification center (Super Admin only)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin/notifications')
    ->middleware(['jwt.auth', 'super.admin'])
    ->group(function () {
        Route::get('/',
            [PlatformNotificationController::class, 'index'])
            ->middleware('permission:platform.notifications');

        Route::get('/unread-count',
            [PlatformNotificationController::class, 'unreadCount'])
            ->middleware('permission:platform.notifications');

        Route::post('/mark-all-read',
            [PlatformNotificationController::class, 'markAllAsRead'])
            ->middleware('permission:platform.notifications');

        Route::patch('/{notification}/read',
            [PlatformNotificationController::class, 'markAsRead'])
            ->middleware('permission:platform.notifications');

        Route::patch('/{notification}/unread',
            [PlatformNotificationController::class, 'markAsUnread'])
            ->middleware('permission:platform.notifications');

        Route::delete('/{notification}',
            [PlatformNotificationController::class, 'destroy'])
            ->middleware('permission:platform.notifications');
    });

/*
|--------------------------------------------------------------------------
| Demo Requests (public intake + super admin management)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/demo-requests')->group(function () {
    Route::post('/', [DemoRequestController::class, 'store'])
        ->middleware('throttle:6,1');
});

Route::prefix('v1/admin/demo-requests')
    ->middleware(['jwt.auth', 'super.admin', 'tenant.aware'])
    ->group(function () {
        Route::get('/', [DemoRequestAdminController::class, 'index']);
        Route::get('/{id}', [DemoRequestAdminController::class, 'show']);
        Route::put('/{id}', [DemoRequestAdminController::class, 'update']);
    });
