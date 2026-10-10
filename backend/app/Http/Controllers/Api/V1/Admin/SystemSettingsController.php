<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettings\AiSettingsRequest;
use App\Http\Requests\Admin\SystemSettings\EmailSettingsRequest;
use App\Http\Requests\Admin\SystemSettings\GeneralSettingsRequest;
use App\Http\Requests\Admin\SystemSettings\IntegrationsSettingsRequest;
use App\Http\Requests\Admin\SystemSettings\OperationsSettingsRequest;
use App\Http\Requests\Admin\SystemSettings\SecuritySettingsRequest;
use App\Http\Requests\Admin\SystemSettings\TestEmailRequest;
use App\Models\SystemSetting;
use App\Mail\SystemSettingsTestMail;
use App\Services\AuditLogService;
use App\Services\SystemSettings\IntegrationStatusService;
use App\Services\SystemSettings\SystemSettingsHealthService;
use App\Services\SystemSettings\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

/**
 * Platform-only System Settings API.
 *
 * Category-based endpoints. Every write is validated against the schema
 * allow-list and recorded in the platform audit log.
 */
class SystemSettingsController extends Controller
{
    public function __construct(
        protected SystemSettingsService $settings,
        protected IntegrationStatusService $integrations,
        protected SystemSettingsHealthService $health,
        protected AuditLogService $audit,
    ) {
    }

    /**
     * Settings hub: category metadata only.
     */
    public function index()
    {
        Gate::authorize('viewAny', SystemSetting::class);

        return response()->json([
            'success' => true,
            'data'    => [
                'groups' => $this->settings->schemaGroups(),
            ],
        ]);
    }

    // =========================================================
    // GENERAL
    // =========================================================

    public function general()
    {
        return $this->showGroup('general');
    }

    public function updateGeneral(GeneralSettingsRequest $request)
    {
        return $this->updateGroup('general', $request);
    }

    // =========================================================
    // SECURITY
    // =========================================================

    public function security()
    {
        return $this->showGroup('security');
    }

    public function updateSecurity(SecuritySettingsRequest $request)
    {
        return $this->updateGroup('security', $request);
    }

    // =========================================================
    // AI DEFAULTS
    // =========================================================

    public function ai()
    {
        return $this->showGroup('ai');
    }

    public function updateAi(AiSettingsRequest $request)
    {
        return $this->updateGroup('ai', $request);
    }

    // =========================================================
    // EMAIL
    // =========================================================

    public function email()
    {
        return $this->showGroup('email');
    }

    public function updateEmail(EmailSettingsRequest $request)
    {
        return $this->updateGroup('email', $request);
    }

    public function testEmail(TestEmailRequest $request)
    {
        Gate::authorize('update', SystemSetting::class);

        $this->settings->applyMailConfiguration();
        $email = $this->settings->getEmail();

        $recipient = $request->validated('recipient')
            ?: ($email['email.test_recipient'] ?: null)
            ?: ($email['email.sender_email'] ?: null)
            ?: config('mail.from.address');

        if (! $recipient) {
            return response()->json([
                'success' => false,
                'message' => 'No recipient available. Provide a recipient or configure a sender email.',
            ], 422);
        }

        try {
            Mail::to($recipient)->send(new SystemSettingsTestMail([
                'platform_name' => $this->settings->get('platform.name'),
            ]));

            $this->audit->log(
                'settings.email_tested',
                'system_setting',
                null,
                null,
                ['recipient' => $recipient, 'result' => 'success'],
            );

            return response()->json([
                'success' => true,
                'message' => "Test email sent to {$recipient}.",
                'data'    => ['recipient' => $recipient, 'success' => true],
            ]);
        } catch (\Throwable $e) {
            $this->audit->log(
                'settings.email_tested',
                'system_setting',
                null,
                null,
                ['recipient' => $recipient, 'result' => 'failure', 'error' => $e->getMessage()],
            );

            return response()->json([
                'success' => false,
                'message' => 'Test email failed: ' . $e->getMessage(),
                'data'    => ['recipient' => $recipient, 'success' => false],
            ], 422);
        }
    }

    // =========================================================
    // INTEGRATIONS
    // =========================================================

    public function integrations()
    {
        Gate::authorize('view', SystemSetting::class);

        return response()->json([
            'success' => true,
            'data'    => [
                'providers' => $this->integrations->status(),
            ],
        ]);
    }

    public function updateIntegrations(IntegrationsSettingsRequest $request)
    {
        return $this->updateGroup('integrations', $request);
    }

    public function testIntegration(string $provider)
    {
        Gate::authorize('update', SystemSetting::class);

        if (! $this->integrations->has($provider)) {
            return response()->json([
                'success' => false,
                'message' => "Unknown integration provider: {$provider}.",
            ], 404);
        }

        $result = $this->integrations->test($provider);

        $this->audit->log(
            'settings.integration_tested',
            'system_setting',
            null,
            null,
            ['provider' => $provider, 'success' => $result['success'], 'method' => $result['method']],
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result,
        ]);
    }

    // =========================================================
    // OPERATIONS
    // =========================================================

    public function operations()
    {
        return $this->showGroup('operations');
    }

    public function updateOperations(OperationsSettingsRequest $request)
    {
        return $this->updateGroup('operations', $request);
    }

    public function health()
    {
        Gate::authorize('view', SystemSetting::class);

        return response()->json([
            'success' => true,
            'data'    => $this->health->summary(),
        ]);
    }

    // =========================================================
    // INTERNAL
    // =========================================================

    protected function showGroup(string $group)
    {
        Gate::authorize('view', SystemSetting::class);

        return response()->json([
            'success' => true,
            'data'    => $this->settings->groupPayload($group),
        ]);
    }

    protected function updateGroup(string $group, Request $request)
    {
        Gate::authorize('update', SystemSetting::class);

        $payload = $this->settings->updateGroup($group, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => ucfirst($group) . ' settings updated.',
            'data'    => $payload,
        ]);
    }
}
