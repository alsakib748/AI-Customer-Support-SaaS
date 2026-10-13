<?php

namespace Tests\Feature\Team;

use App\Models\TenantUser;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Workspace owners/managers can mark a team member's email as verified.
 */
class TeamMemberEmailVerificationTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCentralSchema();
        $this->useIsolatedTenantDatabase();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    public function test_owner_can_verify_a_team_member(): void
    {
        $tenant = $this->newTenant();
        $owner = $this->newTenantUser($tenant, 'owner');
        $member = $this->newTenantUser($tenant, 'support_agent', ['email_verified_at' => null]);
        $this->newSubscription($tenant);

        $memberId = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)
            ->value('id');

        $this->establishTenantContext($tenant, $owner);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->postJson("/api/v1/team/members/{$memberId}/verify-email")
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($member->fresh()->email_verified_at, 'the member must now be verified');
    }

    public function test_a_support_agent_cannot_verify_team_members(): void
    {
        $tenant = $this->newTenant();
        $agent = $this->newTenantUser($tenant, 'support_agent');
        $member = $this->newTenantUser($tenant, 'support_agent', ['email_verified_at' => null]);
        $this->newSubscription($tenant);

        $memberId = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)
            ->value('id');

        $this->establishTenantContext($tenant, $agent);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        $this->postJson("/api/v1/team/members/{$memberId}/verify-email")
            ->assertForbidden();

        $this->assertNull($member->fresh()->email_verified_at, 'a denied request must not verify the member');
    }
}
