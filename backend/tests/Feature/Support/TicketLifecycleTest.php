<?php

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
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

    public function test_creating_a_ticket_generates_a_number_and_opens_it(): void
    {
        $this->bootSupportWorkspace();
        $customerId = $this->seedCustomer();

        $response = $this->postJson('/api/v1/tickets', [
            'customer_id' => $customerId,
            'subject' => 'Payment failed',
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high');

        $ticketNumber = $response->json('data.ticket_number');
        $this->assertStringStartsWith('TKT-'.now()->year.'-', $ticketNumber);

        $row = DB::connection('tenant')->table('tickets')->first();
        $this->assertSame($ticketNumber, $row->ticket_number);
        $this->assertNotNull($row->created_by_user_id);
    }

    public function test_ticket_numbers_are_unique_across_tickets(): void
    {
        $this->bootSupportWorkspace();
        $customerId = $this->seedCustomer();

        $first = $this->postJson('/api/v1/tickets', ['customer_id' => $customerId, 'subject' => 'A']);
        $second = $this->postJson('/api/v1/tickets', ['customer_id' => $customerId, 'subject' => 'B']);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertNotSame($first->json('data.ticket_number'), $second->json('data.ticket_number'));
    }

    public function test_creating_a_ticket_requires_a_valid_customer(): void
    {
        $this->bootSupportWorkspace();

        $this->postJson('/api/v1/tickets', ['subject' => 'No customer'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['customer_id']]);

        $this->postJson('/api/v1/tickets', ['customer_id' => 999999, 'subject' => 'Ghost'])
            ->assertStatus(422);

        $this->assertSame(0, DB::connection('tenant')->table('tickets')->count());
    }

    public function test_ticket_status_transitions(): void
    {
        $this->bootSupportWorkspace();
        $ticketId = $this->seedTicket($this->seedCustomer());

        $this->postJson("/api/v1/tickets/{$ticketId}/start")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/tickets/{$ticketId}/pending")
            ->assertOk()->assertJsonPath('data.status', 'pending');

        $this->postJson("/api/v1/tickets/{$ticketId}/resolve")
            ->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull(DB::connection('tenant')->table('tickets')->where('id', $ticketId)->value('resolved_at'));

        $this->postJson("/api/v1/tickets/{$ticketId}/reopen")
            ->assertOk()->assertJsonPath('data.status', 'open');
        $this->assertNull(DB::connection('tenant')->table('tickets')->where('id', $ticketId)->value('resolved_at'));

        $this->postJson("/api/v1/tickets/{$ticketId}/close")
            ->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_an_invalid_transition_is_rejected(): void
    {
        $this->bootSupportWorkspace();
        $ticketId = $this->seedTicket($this->seedCustomer(), ['status' => 'closed']);

        $this->postJson("/api/v1/tickets/{$ticketId}/start")->assertStatus(422);

        $this->assertSame('closed', DB::connection('tenant')->table('tickets')->where('id', $ticketId)->value('status'));
    }

    public function test_assigning_a_ticket_requires_a_member_of_the_workspace(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $ticketId = $this->seedTicket($this->seedCustomer());
        $agent = $this->newTenantUser($tenant, 'support_agent');

        $this->postJson("/api/v1/tickets/{$ticketId}/assign", ['user_id' => $agent->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_user_id', $agent->id);

        $this->postJson("/api/v1/tickets/{$ticketId}/assign", ['user_id' => 424242])
            ->assertStatus(422);
    }

    public function test_comments_can_be_added_and_listed(): void
    {
        $this->bootSupportWorkspace();
        $ticketId = $this->seedTicket($this->seedCustomer());

        $this->postJson("/api/v1/tickets/{$ticketId}/comments", [
            'content' => 'Looking into this now.',
            'type' => 'reply',
        ])->assertStatus(201);

        $this->postJson("/api/v1/tickets/{$ticketId}/comments", [
            'content' => 'Internal: refund approved.',
            'type' => 'internal_note',
        ])->assertStatus(201);

        $this->getJson("/api/v1/tickets/{$ticketId}/comments")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, DB::connection('tenant')->table('ticket_comments')->count());
    }

    public function test_a_support_agent_cannot_delete_ticket_comments(): void
    {
        [$tenant] = $this->bootSupportWorkspace();
        $ticketId = $this->seedTicket($this->seedCustomer());
        $commentId = DB::connection('tenant')->table('ticket_comments')->insertGetId([
            'ticket_id' => $ticketId,
            'user_id' => 1,
            'type' => 'reply',
            'content' => 'hello',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $agent = $this->newTenantUser($tenant, 'support_agent');
        $this->establishTenantContext($tenant, $agent);

        $this->deleteJson("/api/v1/tickets/{$ticketId}/comments/{$commentId}")->assertStatus(403);

        $this->assertDatabaseHas('ticket_comments', ['id' => $commentId, 'deleted_at' => null], 'tenant');
    }

    public function test_deleting_a_comment_rejects_a_mismatched_ticket(): void
    {
        $this->bootSupportWorkspace();
        $customerId = $this->seedCustomer();
        $ticketId = $this->seedTicket($customerId);
        $otherTicketId = $this->seedTicket($customerId);
        $commentId = DB::connection('tenant')->table('ticket_comments')->insertGetId([
            'ticket_id' => $otherTicketId,
            'user_id' => 1,
            'type' => 'reply',
            'content' => 'hello',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/tickets/{$ticketId}/comments/{$commentId}")->assertStatus(404);
    }

    public function test_a_ticket_from_another_tenant_is_not_reachable(): void
    {
        $this->bootSupportWorkspace();
        $ticketId = $this->seedTicket($this->seedCustomer());

        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->getJson("/api/v1/tickets/{$ticketId}")->assertStatus(404);
    }
}
