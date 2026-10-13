<?php

namespace Tests\Feature\Team;

use App\Mail\TeamInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\Team\InvitationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

/**
 * Covers the team-invitation lifecycle: token hashing, email delivery, accept,
 * resend, revoke and portable search.
 */
class TeamInvitationTest extends TestCase
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
        $this->app->instance('current_tenant', Tenant::find($this->tenantId));

        Mail::fake();
    }

    protected function service(): InvitationService
    {
        return new InvitationService(Request::create('/api/v1/team/invitations', 'POST'));
    }

    public function test_creating_an_invitation_hashes_the_token_and_emails_the_link(): void
    {
        $invitation = $this->service()->createInvitation([
            'email' => 'newagent@example.test',
            'role' => 'agent',
        ]);

        $plain = $invitation->plain_token;

        $this->assertNotEmpty($plain);
        $this->assertNotSame($plain, $invitation->token, 'the stored token must never be the plaintext');
        $this->assertSame(hash('sha256', $plain), $invitation->token);

        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($plain) {
            return str_contains($mail->acceptUrl, $plain) && $mail->role === 'agent';
        });
    }

    public function test_accepting_a_valid_invitation_creates_the_membership(): void
    {
        $invitation = $this->service()->createInvitation([
            'email' => 'joiner@example.test',
            'role' => 'agent',
        ]);

        $member = $this->service()->acceptInvitation($invitation->plain_token, [
            'email' => 'joiner@example.test',
            'first_name' => 'Joiner',
            'last_name' => 'Person',
            'password' => 'SecretPass!123',
        ]);

        $this->assertSame('agent', $member->role);
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenantId,
            'role' => 'agent',
        ], 'central');
        $this->assertNotNull(TenantInvitation::find($invitation->id)->accepted_at);

        // Serialising the member (as the accept endpoint does) must resolve
        // every appended accessor, including `avatar`.
        $this->assertArrayHasKey('avatar', $member->toArray());
    }

    public function test_accepting_with_a_mismatched_email_is_rejected(): void
    {
        $invitation = $this->service()->createInvitation([
            'email' => 'invitee@example.test',
            'role' => 'agent',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->acceptInvitation($invitation->plain_token, [
            'email' => 'someoneelse@example.test',
        ]);
    }

    public function test_accepting_an_invitation_works_without_tenant_context(): void
    {
        $invitation = $this->service()->createInvitation([
            'email' => 'publicjoiner@example.test',
            'role' => 'agent',
        ]);

        // Simulate the public accept route: no authenticated user and no tenant
        // resolved for the request. Constructing the service must not throw
        // (it previously did, surfacing as a 500 on the accept endpoint).
        auth()->forgetGuards();
        $this->app->forgetInstance('current_tenant');

        $service = new InvitationService(
            Request::create('/api/v1/team/invitations/accept/'.$invitation->plain_token, 'POST')
        );

        $member = $service->acceptInvitation($invitation->plain_token, [
            'email' => 'publicjoiner@example.test',
            'first_name' => 'Public',
            'last_name' => 'Joiner',
            'password' => 'SecretPass!123',
        ]);

        $this->assertSame('agent', $member->role);
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenantId,
            'role' => 'agent',
        ], 'central');
        $this->assertNotNull(TenantInvitation::find($invitation->id)->accepted_at);
    }

    public function test_resending_regenerates_the_token_and_emails_again(): void
    {
        $invitation = $this->service()->createInvitation([
            'email' => 'resend@example.test',
            'role' => 'viewer',
        ]);

        $originalToken = $invitation->token;

        $resent = $this->service()->resendInvitation($invitation->id);

        $this->assertNotSame($originalToken, $resent->token);
        $this->assertSame(hash('sha256', $resent->plain_token), $resent->token);

        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($resent) {
            return str_contains($mail->acceptUrl, $resent->plain_token);
        });
    }

    public function test_revoked_invitations_can_no_longer_be_accepted(): void
    {
        $invitation = $this->service()->createInvitation([
            'email' => 'revoked@example.test',
            'role' => 'agent',
        ]);

        $this->service()->revokeInvitation($invitation->id);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->acceptInvitation($invitation->plain_token, [
            'email' => 'revoked@example.test',
        ]);
    }

    public function test_invitation_search_is_case_insensitive(): void
    {
        TenantInvitation::create([
            'tenant_id' => $this->tenantId,
            'email' => 'MixedCase@Example.test',
            'role' => 'agent',
            'token' => hash('sha256', 'seed'),
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame(1, TenantInvitation::search('mixedcase')->count());
    }
}
