<?php

namespace Tests\Feature\Support;

use App\Events\Message\MessageCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class MessageThreadTest extends TestCase
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

    public function test_an_agent_reply_is_recorded_and_touches_the_conversation(): void
    {
        Event::fake([MessageCreated::class]);
        $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer(), ['last_message_at' => now()->subDay()]);

        $response = $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'content' => 'Thanks for reaching out, how can I help?',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.sender.type', 'agent')
            ->assertJsonPath('data.is_internal', false);

        Event::assertDispatched(MessageCreated::class);
        $this->assertSame(1, DB::connection('tenant')->table('messages')->count());
        $this->assertTrue(
            DB::connection('tenant')->table('conversations')->where('id', $conversationId)->value('last_message_at') > now()->subHour()
        );
    }

    public function test_a_message_requires_content(): void
    {
        $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer());

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['content' => ''])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content']]);

        $this->assertSame(0, DB::connection('tenant')->table('messages')->count());
    }

    public function test_an_internal_note_is_marked_internal(): void
    {
        Event::fake([MessageCreated::class]);
        $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer());

        $this->postJson("/api/v1/conversations/{$conversationId}/notes", [
            'content' => 'Customer is on the enterprise plan.',
        ])->assertStatus(201)
            ->assertJsonPath('data.is_internal', true)
            ->assertJsonPath('data.message_type', 'internal_note');
    }

    public function test_agents_see_internal_notes_but_plain_members_do_not(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer());
        $this->seedMessage($conversationId, ['sender_type' => 'customer', 'content' => 'public question']);
        $this->seedMessage($conversationId, [
            'sender_type' => 'agent',
            'message_type' => 'internal_note',
            'content' => 'secret note',
            'is_internal' => true,
        ]);

        $ownerView = $this->getJson("/api/v1/conversations/{$conversationId}/messages");
        $ownerView->assertOk();
        $this->assertCount(2, $ownerView->json('data'));

        $member = $this->newTenantUser($tenant, 'no_privileges');
        $this->establishTenantContext($tenant, $member);

        $memberView = $this->getJson("/api/v1/conversations/{$conversationId}/messages");
        $memberView->assertOk();
        $this->assertCount(1, $memberView->json('data'));
        $this->assertSame('public question', $memberView->json('data.0.content'));
    }

    public function test_members_without_reply_permission_cannot_send(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer());

        $member = $this->newTenantUser($tenant, 'no_privileges');
        $this->establishTenantContext($tenant, $member);

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['content' => 'hi'])
            ->assertStatus(422);

        $this->assertSame(0, DB::connection('tenant')->table('messages')->count());
    }

    public function test_an_agent_message_can_be_deleted_but_an_ai_message_cannot(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer());
        $agentMessage = $this->seedMessage($conversationId, ['sender_type' => 'agent', 'sender_id' => 1]);
        $aiMessage = $this->seedMessage($conversationId, ['sender_type' => 'ai']);

        $this->deleteJson("/api/v1/messages/{$agentMessage}")->assertOk();
        $this->assertSoftDeleted('messages', ['id' => $agentMessage], 'tenant');

        $this->deleteJson("/api/v1/messages/{$aiMessage}")->assertStatus(422);
        $this->assertDatabaseHas('messages', ['id' => $aiMessage, 'deleted_at' => null], 'tenant');
    }

    public function test_a_support_agent_cannot_delete_messages(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $conversationId = $this->seedConversation($this->seedCustomer());
        $messageId = $this->seedMessage($conversationId, ['sender_type' => 'agent', 'sender_id' => 1]);

        $agent = $this->newTenantUser($tenant, 'support_agent');
        $this->establishTenantContext($tenant, $agent);

        $this->deleteJson("/api/v1/messages/{$messageId}")->assertStatus(422);
        $this->assertDatabaseHas('messages', ['id' => $messageId, 'deleted_at' => null], 'tenant');
    }

    public function test_a_message_from_another_tenant_is_not_reachable(): void
    {
        $this->bootSupportWorkspace();
        $messageId = $this->seedMessage($this->seedConversation($this->seedCustomer()));

        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->getJson("/api/v1/messages/{$messageId}")->assertStatus(404);
    }
}
