<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAnalyticsDomain;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Tenant-scoped notification feed for an authenticated member
 * (`GET /v1/notifications`, mark-one-read, mark-all-read).
 */
class TenantNotificationApiTest extends TestCase
{
    use InteractsWithAnalyticsDomain;
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedTenantDatabase();

        config(['database.default' => 'central']);
        DB::purge('central');

        $this->createCentralSchema();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();

        parent::tearDown();
    }

    protected function seedNotification(User $user, array $overrides = []): string
    {
        $notification = $user->notifications()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\SessionNotification',
            'data' => ['message' => 'Hello'],
            'read_at' => null,
        ], $overrides));

        return $notification->id;
    }

    protected function readAt(string $id): mixed
    {
        return DB::connection('central')
            ->table('notifications')
            ->where('id', $id)
            ->value('read_at');
    }

    public function test_index_lists_only_the_authenticated_users_notifications(): void
    {
        [$tenant, $user] = $this->bootAnalyticsWorkspace();
        $other = $this->newTenantUser($tenant, 'support_agent');

        $this->seedNotification($user);
        $this->seedNotification($user);
        $this->seedNotification($other);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 2);
    }

    public function test_mark_as_read_marks_a_notification_as_read(): void
    {
        [, $user] = $this->bootAnalyticsWorkspace();
        $id = $this->seedNotification($user);

        $this->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($this->readAt($id));
    }

    public function test_mark_all_as_read_marks_every_notification_read(): void
    {
        [, $user] = $this->bootAnalyticsWorkspace();
        $first = $this->seedNotification($user);
        $second = $this->seedNotification($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($this->readAt($first));
        $this->assertNotNull($this->readAt($second));
    }

    public function test_marking_another_users_notification_has_no_side_effect(): void
    {
        [$tenant] = $this->bootAnalyticsWorkspace();
        $other = $this->newTenantUser($tenant, 'support_agent');
        $foreignId = $this->seedNotification($other);

        $this->postJson("/api/v1/notifications/{$foreignId}/read")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($this->readAt($foreignId));
    }
}
