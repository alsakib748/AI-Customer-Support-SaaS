<?php

namespace Tests\Feature\Notifications;

use App\Http\Middleware\JWTAuthMiddleware;
use App\Models\User;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

class PlatformNotificationApiTest extends TestCase
{
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCentralNotificationSchema();
        $this->ensureSuperAdminRole();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Authenticate as the acting user without a real JWT; the middleware
        // that would parse the bearer token is disabled per-test below.
        $this->withoutMiddleware(JWTAuthMiddleware::class);
    }

    protected function as(User $user): static
    {
        $this->actingAs($user, 'api');

        return $this;
    }

    protected function seedNotification(User $user, array $data, ?string $readAt = null)
    {
        return $user->notifications()->create([
            'id'      => (string) Str::uuid(),
            'type'    => PlatformNotification::class,
            'data'    => $data,
            'read_at' => $readAt,
        ]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'scope'       => 'platform',
            'event_key'   => 'billing.payment_failed',
            'category'    => NotificationCategory::Billing->value,
            'severity'    => NotificationSeverity::Error->value,
            'title'       => 'Payment failed',
            'message'     => 'A payment failed.',
            'action_url'  => '/admin/billing/payments',
            'entity_type' => 'payment',
            'entity_id'   => '42',
        ], $overrides);
    }

    public function test_super_admin_can_list_notifications_with_meta(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seedNotification($admin, $this->payload());
        $this->seedNotification($admin, $this->payload(), now()->toDateTimeString());

        $response = $this->as($admin)->getJson('/api/v1/admin/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category', 'billing')
            ->assertJsonPath('data.0.severity', 'error');
    }

    public function test_unread_count_endpoint(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seedNotification($admin, $this->payload());
        $this->seedNotification($admin, $this->payload());

        $this->as($admin)
            ->getJson('/api/v1/admin/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_mark_one_notification_as_read_then_unread(): void
    {
        $admin = $this->makeSuperAdmin();
        $notification = $this->seedNotification($admin, $this->payload());

        $this->as($admin)
            ->patchJson("/api/v1/admin/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);

        $this->as($admin)
            ->patchJson("/api/v1/admin/notifications/{$notification->id}/unread")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_only_affects_current_user(): void
    {
        $adminA = $this->makeSuperAdmin(['email' => 'a@example.test']);
        $adminB = $this->makeSuperAdmin(['email' => 'b@example.test']);

        $this->seedNotification($adminA, $this->payload());
        $this->seedNotification($adminA, $this->payload());
        $this->seedNotification($adminB, $this->payload());

        $this->as($adminA)
            ->postJson('/api/v1/admin/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $adminA->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $adminB->fresh()->unreadNotifications()->count());
    }

    public function test_delete_notification(): void
    {
        $admin = $this->makeSuperAdmin();
        $notification = $this->seedNotification($admin, $this->payload());

        $this->as($admin)
            ->deleteJson("/api/v1/admin/notifications/{$notification->id}")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id], 'central');
    }

    public function test_category_filter_returns_only_matching_notifications(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seedNotification($admin, $this->payload(['category' => NotificationCategory::Billing->value]));
        $this->seedNotification($admin, $this->payload([
            'category' => NotificationCategory::Tenant->value,
            'event_key' => 'tenant.suspended',
        ]));

        $this->as($admin)
            ->getJson('/api/v1/admin/notifications?category=tenant')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'tenant');
    }

    public function test_super_admin_cannot_touch_another_users_notification(): void
    {
        $adminA = $this->makeSuperAdmin(['email' => 'a@example.test']);
        $adminB = $this->makeSuperAdmin(['email' => 'b@example.test']);
        $notification = $this->seedNotification($adminB, $this->payload());

        $this->as($adminA)
            ->patchJson("/api/v1/admin/notifications/{$notification->id}/read")
            ->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $user = $this->makeUser(['email' => 'tenant@example.test']);

        $this->as($user)
            ->getJson('/api/v1/admin/notifications')
            ->assertStatus(403);
    }

    public function test_legacy_payload_is_normalized(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seedNotification($admin, [
            'type'    => 'payment_failed',
            'message' => 'Legacy message',
        ]);

        $response = $this->as($admin)->getJson('/api/v1/admin/notifications');

        $response->assertOk()
            ->assertJsonPath('data.0.category', 'billing')
            ->assertJsonPath('data.0.severity', 'error')
            ->assertJsonPath('data.0.title', 'Payment Failed')
            ->assertJsonPath('data.0.message', 'Legacy message');
    }

    public function test_malformed_action_url_is_neutralized(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seedNotification($admin, $this->payload([
            'action_url' => 'https://evil.example.test/phish',
        ]));

        $this->as($admin)
            ->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.action_url', null);
    }
}
