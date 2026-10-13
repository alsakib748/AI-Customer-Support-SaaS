<?php

namespace Tests\Feature\Ops;

use App\Mail\TenantMemberAddedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

/**
 * Covers the direct "add existing user to a tenant" flow: the membership is
 * persisted and the member is emailed a dashboard link.
 */
class TenantInvitationEmailTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected string $tenantId;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'tenancy.database.central_connection' => 'central',
            'cache.default' => 'array',
            'app.frontend_url' => 'https://app.example.test',
        ]);

        DB::purge('central');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createCentralSchema();

        $this->tenantId = (string) Str::uuid();

        Tenant::create([
            'id' => $this->tenantId,
            'name' => 'Acme Corp',
            'slug' => 'acme'.uniqid(),
            'status' => 'active',
        ]);

        $this->owner = User::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'Person',
            'email' => 'owner@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
        ]);

        setPermissionsTeamId($this->tenantId);
        $this->owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->owner);

        Mail::fake();
    }

    public function test_inviting_an_existing_user_persists_membership_and_emails_a_dashboard_link(): void
    {
        $invitee = User::query()->create([
            'first_name' => 'Invitee',
            'last_name' => 'Person',
            'email' => 'invitee@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
        ]);

        app(TenantService::class)->inviteUser($this->tenantId, [
            'email' => 'invitee@example.test',
            'role' => 'agent',
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenantId,
            'user_id' => $invitee->id,
            'role' => 'agent',
        ], 'central');

        Mail::assertQueued(TenantMemberAddedMail::class, function (TenantMemberAddedMail $mail) {
            return $mail->tenantName === 'Acme Corp'
                && $mail->userName === 'Invitee Person'
                && str_contains($mail->dashboardUrl, 'https://app.example.test/dashboard')
                && $mail->inviterName === $this->owner->full_name;
        });
    }

    public function test_the_member_added_mailable_renders_the_tenant_and_link(): void
    {
        $mail = new TenantMemberAddedMail(
            userName: 'Invitee Person',
            tenantName: 'Acme Corp',
            dashboardUrl: 'https://app.example.test/dashboard',
            inviterName: 'Owner Person',
        );

        $html = $mail->render();

        $this->assertStringContainsString('Acme Corp', $html);
        $this->assertStringContainsString('https://app.example.test/dashboard', $html);
        $this->assertStringContainsString('Owner Person', $html);
    }
}
