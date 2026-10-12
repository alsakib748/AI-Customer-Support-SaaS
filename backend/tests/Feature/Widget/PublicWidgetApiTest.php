<?php

namespace Tests\Feature\Widget;

use App\Models\Tenant;
use App\Models\Tenant\ChatWidget;
use App\Models\Tenant\WidgetSession;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Public (unauthenticated) chat widget API.
 *
 * The widget routes live outside the jwt/tenant middleware stack: the tenant
 * is resolved from the public widget key (or the session token) inside the
 * controller. These tests therefore point the tenant connection at an isolated
 * file database and deliberately do NOT bind a `current_tenant` context, so we
 * exercise the controller's own resolution logic.
 */
class PublicWidgetApiTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedTenantDatabase();

        config(['database.default' => 'central']);
        DB::purge('central');

        $this->createCentralSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();

        parent::tearDown();
    }

    /**
     * Create one tenant with its own database and an active widget, then point
     * the tenant connection at it. No container tenant binding is established.
     *
     * @return array{0: Tenant, 1: ChatWidget}
     */
    protected function bootWidgetWorkspace(array $widgetOverrides = []): array
    {
        $tenant = $this->newTenant();

        $this->prepareTenantDatabase($tenant);
        $this->pointTenantConnectionAt($this->tenantDatabaseFiles[$tenant->id]);

        $widget = ChatWidget::query()->create(array_merge([
            'name' => 'Support Widget',
            'status' => 'active',
            'allowed_origins' => ['https://allowed.example'],
        ], $widgetOverrides));

        return [$tenant, $widget];
    }

    protected function seedWidgetSession(ChatWidget $widget, array $overrides = []): WidgetSession
    {
        return WidgetSession::query()->create(array_merge([
            'widget_id' => $widget->id,
            'last_seen_at' => now(),
        ], $overrides));
    }

    public function test_bootstrap_returns_public_widget_configuration(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();

        $response = $this->postJson('/api/v1/widget/bootstrap', [
            'widget_id' => $widget->public_key,
            'origin' => 'https://allowed.example',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.widget.public_key', $widget->public_key)
            ->assertJsonPath('data.widget.name', 'Support Widget')
            ->assertJsonStructure([
                'data' => [
                    'widget' => ['id', 'public_key', 'name', 'header_title', 'position', 'show_branding'],
                    'features' => ['attachments', 'voice', 'ai', 'realtime', 'streaming'],
                ],
            ]);
    }

    public function test_bootstrap_reads_origin_from_the_request_header(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();

        $response = $this->postJson(
            '/api/v1/widget/bootstrap',
            ['widget_id' => $widget->public_key],
            ['Origin' => 'https://allowed.example']
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_bootstrap_returns_not_found_for_an_unknown_widget(): void
    {
        $this->bootWidgetWorkspace();

        $this->postJson('/api/v1/widget/bootstrap', [
            'widget_id' => 'cw_does_not_exist',
        ])->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_bootstrap_returns_not_found_for_an_inactive_widget(): void
    {
        [, $widget] = $this->bootWidgetWorkspace(['status' => 'disabled']);

        $this->postJson('/api/v1/widget/bootstrap', [
            'widget_id' => $widget->public_key,
        ])->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_bootstrap_rejects_a_disallowed_origin(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();

        $response = $this->postJson('/api/v1/widget/bootstrap', [
            'widget_id' => $widget->public_key,
            'origin' => 'https://evil.example',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('origin');
    }

    public function test_bootstrap_requires_a_widget_id(): void
    {
        $this->postJson('/api/v1/widget/bootstrap', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('widget_id');
    }

    /**
     * DEFECT (P0): WidgetController::session() reads app('current_tenant')
     * before it resolves the widget/tenant. On the public widget route nothing
     * binds `current_tenant`, so the value is null and
     * BillingLimitService::enforce(Tenant $tenant, ...) raises a TypeError that
     * the surrounding `catch (\Exception)` does not catch -> HTTP 500. Every
     * widget session bootstrap therefore fails in production.
     */
    public function test_session_returns_a_server_error_because_no_tenant_context_is_initialized(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();

        $response = $this->postJson('/api/v1/widget/session', [
            'widget_id' => $widget->public_key,
        ]);

        $response->assertStatus(500);
    }

    public function test_session_still_validates_the_widget_id(): void
    {
        $this->postJson('/api/v1/widget/session', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('widget_id');
    }

    public function test_get_messages_requires_a_session_token(): void
    {
        $this->bootWidgetWorkspace();

        $this->getJson('/api/v1/widget/messages')
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_token');
    }

    public function test_get_messages_returns_an_empty_list_for_a_session_without_a_conversation(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();
        $session = $this->seedWidgetSession($widget);

        $response = $this->getJson(
            '/api/v1/widget/messages?session_token='.$session->session_token,
            ['Origin' => 'https://allowed.example']
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_get_conversation_requires_a_session_token(): void
    {
        $this->bootWidgetWorkspace();

        $this->getJson('/api/v1/widget/conversation')
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_token');
    }

    public function test_get_conversation_returns_null_without_an_active_conversation(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();
        $session = $this->seedWidgetSession($widget);

        $response = $this->getJson(
            '/api/v1/widget/conversation?session_token='.$session->session_token,
            ['Origin' => 'https://allowed.example']
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_send_message_requires_content(): void
    {
        $this->bootWidgetWorkspace();

        $this->postJson('/api/v1/widget/messages', [
            'session_token' => 'ws_missing',
        ])->assertStatus(422)->assertJsonValidationErrors('content');
    }

    public function test_send_message_returns_not_found_for_an_unknown_session(): void
    {
        $this->bootWidgetWorkspace();

        $this->postJson('/api/v1/widget/messages', [
            'session_token' => 'ws_does_not_exist',
            'content' => 'Hello?',
        ])->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_widget_rate_limiter_blocks_after_the_limit_is_exceeded(): void
    {
        [, $widget] = $this->bootWidgetWorkspace();

        $payload = [
            'widget_id' => $widget->public_key,
            'origin' => 'https://allowed.example',
        ];

        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/widget/bootstrap', $payload)->assertOk();
        }

        $this->postJson('/api/v1/widget/bootstrap', $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
