<?php
// app/Services/Conversation/ConversationService.php

namespace App\Services\Conversation;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    /**
     * Get list of conversations with filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->with('customer');

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Filter by priority
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        // Filter by channel
        if (!empty($filters['channel'])) {
            $query->byChannel($filters['channel']);
        }

        // Filter by assigned user
        if (!empty($filters['assigned_user_id'])) {
            $query->assignedTo($filters['assigned_user_id']);
        }

        // Filter unassigned
        if (!empty($filters['unassigned']) && $filters['unassigned'] === 'true') {
            $query->unassigned();
        }

        // Sort
        $sortField = $filters['sort'] ?? 'last_message_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = [
            'last_message_at',
            'created_at',
            'updated_at',
            'priority',
            'status',
            'subject',
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('last_message_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    /**
     * Get a single conversation
     */
    public function getConversation(int $id): Conversation
    {
        return Conversation::with('customer')
            ->withCount('messages')
            ->findOrFail($id);
    }

    /**
     * Get conversation by customer and channel (for auto-creation)
     */
    public function getActiveConversationForCustomer(int $customerId, string $channel = 'web'): ?Conversation
    {
        return Conversation::where('customer_id', $customerId)
            ->where('channel', $channel)
            ->whereIn('status', ['open', 'pending'])
            ->latest('last_message_at')
            ->first();
    }

    /**
     * Create a new conversation
     */
    public function create(array $data): Conversation
    {
        $customer = Customer::findOrFail($data['customer_id']);

        return DB::transaction(function () use ($data, $customer) {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'subject' => $data['subject'] ?? null,
                'channel' => $data['channel'] ?? 'web',
                'status' => 'open',
                'priority' => $data['priority'] ?? 'normal',
                'started_at' => now(),
                'last_message_at' => now(),
                'metadata' => $data['metadata'] ?? null,
            ]);

            Log::info('Conversation created', [
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
            ]);

            return $conversation;
        });
    }

    /**
     * Find or create active conversation for customer
     */
    public function findOrCreateForCustomer(int $customerId, array $data = []): Conversation
    {
        $channel = $data['channel'] ?? 'web';

        // Try to find active conversation
        $conversation = $this->getActiveConversationForCustomer($customerId, $channel);

        if ($conversation) {
            return $conversation;
        }

        // Create new conversation
        return $this->create([
            'customer_id' => $customerId,
            'subject' => $data['subject'] ?? 'New Conversation',
            'channel' => $channel,
            'priority' => $data['priority'] ?? 'normal',
        ]);
    }

    /**
     * Update a conversation
     */
    public function update(Conversation $conversation, array $data): Conversation
    {
        $conversation->update($data);

        Log::info('Conversation updated', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'changes' => $data,
        ]);

        return $conversation->fresh();
    }

    /**
     * Delete a conversation (soft delete)
     */
    public function delete(Conversation $conversation): bool
    {
        $conversation->delete();

        Log::info('Conversation deleted', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Resolve a conversation
     */
    public function resolve(Conversation $conversation): Conversation
    {
        if (!$conversation->canResolve()) {
            throw new \Exception('Conversation cannot be resolved in its current state.');
        }

        $conversation->resolve();

        Log::info('Conversation resolved', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
        ]);

        return $conversation->fresh();
    }

    /**
     * Reopen a conversation
     */
    public function reopen(Conversation $conversation): Conversation
    {
        if (!$conversation->canReopen()) {
            throw new \Exception('Conversation cannot be reopened in its current state.');
        }

        $conversation->reopen();

        Log::info('Conversation reopened', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
        ]);

        return $conversation->fresh();
    }

    /**
     * Close a conversation
     */
    public function close(Conversation $conversation): Conversation
    {
        if (!$conversation->canClose()) {
            throw new \Exception('Conversation cannot be closed in its current state.');
        }

        $conversation->close();

        Log::info('Conversation closed', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
        ]);

        return $conversation->fresh();
    }

    /**
     * Assign a conversation to a user
     */
    public function assign(Conversation $conversation, int $userId): Conversation
    {
        if (!$conversation->canAssign()) {
            throw new \Exception('Conversation cannot be assigned in its current state.');
        }

        // Verify user belongs to tenant
        $this->verifyUserInTenant($userId);

        $conversation->assign($userId);

        Log::info('Conversation assigned', [
            'conversation_id' => $conversation->id,
            'assigned_user_id' => $userId,
            'user_id' => auth()->id(),
        ]);

        return $conversation->fresh();
    }

    /**
     * Unassign a conversation
     */
    public function unassign(Conversation $conversation): Conversation
    {
        $conversation->unassign();

        Log::info('Conversation unassigned', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
        ]);

        return $conversation->fresh();
    }

    /**
     * Get conversation statistics
     */
    public function getStatistics(): array
    {
        $query = Conversation::query();

        return [
            'total' => $query->count(),
            'open' => (clone $query)->where('status', 'open')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'unassigned' => (clone $query)->whereNull('assigned_user_id')->count(),
            'urgent' => (clone $query)->where('priority', 'urgent')->count(),
            'high' => (clone $query)->where('priority', 'high')->count(),
            'by_channel' => (clone $query)
                ->selectRaw('channel, count(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
        ];
    }

    /**
     * Verify user belongs to current tenant
     */
    protected function verifyUserInTenant(int $userId): void
    {
        $tenant = app('current_tenant');

        if (!$tenant) {
            throw new \Exception('No tenant context found.');
        }

        $exists = \DB::connection('central')
            ->table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw new \Exception('User does not belong to this workspace.');
        }
    }
}
