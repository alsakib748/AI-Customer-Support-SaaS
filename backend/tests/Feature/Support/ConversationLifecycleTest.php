<?php

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class ConversationLifecycleTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithSupportDomain;
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

    public function test_creating_a_conversation_for_a_customer_opens_it(): void
    {
        $this->bootSupportWorkspace();
        $customerId = $this->seedCustomer();

        $response = $this->postJson('/api/v1/conversations', [
            'customer_id' => $customerId,
            'channel' => 'web',
            'subject' => 'I cannot log in',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.customer.id', $customerId)
            ->assertJsonPath('data.channel', 'web');

        $this->assertSame(1, DB::connection('tenant')->table('conversations')->count());
        $row = DB::connection('tenant')->table('conversations')->first();
        $this->assertNotNull($row->started_at);
    }

    public function test_creating_a_conversation_requires_an_existing_customer(): void
    {
        $this->bootSupportWorkspace();

        $response = $this->postJson('/api/v1/conversations', [
            'customer_id' => 999999,
            'channel' => 'web',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors' => ['customer_id']]);
        $this->assertSame(0, DB::connection('tenant')->table('conversations')->count());
    }

    public function test_index_only_returns_the_current_tenants_conversations(): void
    {
        $this->bootSupportWorkspace();
        $customerId = $this->seedCustomer();
        $this->seedConversation($customerId, ['subject' => 'Mine']);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertOk()->assertJsonPath('data.0.subject', 'Mine');
        $this->assertSame(1, DB::connection('tenant')->table('conversations')->count());
    }

    public function test_status_transitions_resolve_reopen_and_close(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->seedConversation($this->seedCustomer());

        $this->postJson("/api/v1/conversations/{$id}/resolve")
            ->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->postJson("/api/v1/conversations/{$id}/reopen")
            ->assertOk()->assertJsonPath('data.status', 'open');

        $this->postJson("/api/v1/conversations/{$id}/resolve")->assertOk();
        $this->postJson("/api/v1/conversations/{$id}/close")
            ->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_resolving_a_closed_conversation_is_rejected(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->seedConversation($this->seedCustomer(), ['status' => 'closed']);

        $this->postJson("/api/v1/conversations/{$id}/resolve")->assertStatus(422);

        $this->assertSame('closed', DB::connection('tenant')->table('conversations')->where('id', $id)->value('status'));
    }

    public function test_assigning_a_conversation_to_a_member_of_the_workspace(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $id = $this->seedConversation($this->seedCustomer());

        $agent = $this->newTenantUser($tenant, 'support_agent');

        $this->postJson("/api/v1/conversations/{$id}/assign", ['user_id' => $agent->id])
            ->assertOk()->assertJsonPath('data.assigned_user_id', $agent->id);
    }

    public function test_assigning_a_conversation_to_a_non_member_is_rejected(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->seedConversation($this->seedCustomer());

        $this->postJson("/api/v1/conversations/{$id}/assign", ['user_id' => 424242])
            ->assertStatus(422);

        $this->assertNull(DB::connection('tenant')->table('conversations')->where('id', $id)->value('assigned_user_id'));
    }

    public function test_unassign_clears_the_assignee(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $agent = $this->newTenantUser($tenant, 'support_agent');
        $id = $this->seedConversation($this->seedCustomer(), ['assigned_user_id' => $agent->id]);

        $this->postJson("/api/v1/conversations/{$id}/unassign")->assertOk();

        $this->assertNull(DB::connection('tenant')->table('conversations')->where('id', $id)->value('assigned_user_id'));
    }

    public function test_a_conversation_from_another_tenant_is_not_visible(): void
    {
        [, $ownerA] = $this->bootSupportWorkspace();
        $id = $this->seedConversation($this->seedCustomer());

        // Move to a second tenant; the same numeric id must resolve to nothing.
        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->getJson("/api/v1/conversations/{$id}")->assertStatus(404);
        $this->assertSame(0, DB::connection('tenant')->table('conversations')->count());
    }

    public function test_deleting_a_conversation_soft_deletes_it(): void
    {
        $this->bootSupportWorkspace();
        $id = $this->seedConversation($this->seedCustomer());

        $this->deleteJson("/api/v1/conversations/{$id}")->assertOk();

        $this->assertSoftDeleted('conversations', ['id' => $id], 'tenant');
    }
}
