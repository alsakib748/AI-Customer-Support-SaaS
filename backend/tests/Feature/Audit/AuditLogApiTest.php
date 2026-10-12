<?php

namespace Tests\Feature\Audit;

use App\Http\Middleware\JWTAuthMiddleware;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');

        $this->createCentralNotificationSchema();
        $this->createAuditLogSchema();
        $this->ensureSuperAdminRole();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withoutMiddleware(JWTAuthMiddleware::class);
    }

    protected function as(User $user): static
    {
        $this->actingAs($user, 'api');

        return $this;
    }

    protected function log(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'tenant_id' => 'tenant-a',
            'user_id' => null,
            'action' => 'customer_created',
            'resource_type' => 'customer',
            'resource_id' => '1',
            'old_values' => null,
            'new_values' => ['name' => 'Acme'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'metadata' => ['event' => 'created'],
        ], $overrides));
    }

    public function test_super_admin_can_list_platform_audit_logs_with_pagination_meta(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->log();
        $this->log(['action' => 'ticket_created', 'resource_type' => 'ticket']);
        $this->log(['action' => 'user_logged_in']);

        $response = $this->as($admin)->getJson('/api/v1/admin/audit-logs?per_page=2');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_audit_logs_can_be_filtered_by_action(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->log(['action' => 'customer_created']);
        $this->log(['action' => 'ticket_created', 'resource_type' => 'ticket']);

        $response = $this->as($admin)->getJson('/api/v1/admin/audit-logs?action=ticket_created');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('ticket_created', $response->json('data.0.action'));
    }

    public function test_audit_log_search_is_case_insensitive(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->log(['action' => 'Customer_Created']);
        $this->log(['action' => 'ticket_created', 'resource_type' => 'ticket']);

        $response = $this->as($admin)->getJson('/api/v1/admin/audit-logs?search=CUSTOMER');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_audit_logs_can_be_scoped_to_a_tenant(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->log(['tenant_id' => 'tenant-a']);
        $this->log(['tenant_id' => 'tenant-b']);

        $response = $this->as($admin)->getJson('/api/v1/admin/audit-logs?tenant_id=tenant-a');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('tenant-a', $response->json('data.0.tenant_id'));
    }

    public function test_statistics_returns_totals_and_action_breakdown(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->log(['action' => 'customer_created']);
        $this->log(['action' => 'customer_created']);
        $this->log(['action' => 'ticket_created', 'resource_type' => 'ticket']);

        $response = $this->as($admin)->getJson('/api/v1/admin/audit-logs/statistics');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_action.customer_created', 2)
            ->assertJsonPath('data.by_action.ticket_created', 1);
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $user = $this->makeUser();

        $this->as($user)
            ->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(403);
    }
}
