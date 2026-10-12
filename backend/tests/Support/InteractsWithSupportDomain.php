<?php

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures for the core support workflow (customers / conversations / messages /
 * tickets) executed inside an isolated tenant database.
 */
trait InteractsWithSupportDomain
{
    /**
     * Boot a tenant workspace with an owner and an active subscription, and
     * authenticate the owner with the tenant context established.
     *
     * @return array{0: Tenant, 1: User}
     */
    protected function bootSupportWorkspace(string $role = 'owner'): array
    {
        $tenant = $this->newTenant();
        $user = $this->newTenantUser($tenant, $role);
        $this->newSubscription($tenant);

        $this->establishTenantContext($tenant, $user);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        return [$tenant, $user];
    }

    protected function seedCustomer(array $overrides = []): int
    {
        return (int) DB::connection('tenant')->table('customers')->insertGetId(array_merge([
            'first_name' => 'Cust',
            'last_name' => 'Omer',
            'email' => 'cust'.uniqid().'@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function seedConversation(int $customerId, array $overrides = []): int
    {
        return (int) DB::connection('tenant')->table('conversations')->insertGetId(array_merge([
            'customer_id' => $customerId,
            'subject' => 'Help needed',
            'channel' => 'web',
            'status' => 'open',
            'priority' => 'normal',
            'started_at' => now(),
            'last_message_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function seedTicket(int $customerId, array $overrides = []): int
    {
        return (int) DB::connection('tenant')->table('tickets')->insertGetId(array_merge([
            'ticket_number' => 'TKT-'.now()->year.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'customer_id' => $customerId,
            'subject' => 'Something is broken',
            'status' => 'open',
            'priority' => 'normal',
            'type' => 'general',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function seedMessage(int $conversationId, array $overrides = []): int
    {
        return (int) DB::connection('tenant')->table('messages')->insertGetId(array_merge([
            'conversation_id' => $conversationId,
            'sender_type' => 'customer',
            'sender_id' => null,
            'message_type' => 'text',
            'content' => 'Hello there',
            'is_internal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
