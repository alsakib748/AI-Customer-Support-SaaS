<?php
// app/Ai/Context/CustomerContextBuilder.php

namespace App\Ai\Context;

use App\Models\Tenant\Customer;
use Illuminate\Support\Facades\Log;

class CustomerContextBuilder
{
    /**
     * Build customer context
     */
    public function build(Customer $customer): array
    {
        try {
            return [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company' => $customer->company_name,
                'status' => $customer->status,
                'tags' => $customer->tags ?? [],
                'notes' => $customer->notes,
                'timezone' => $customer->timezone,
                'language' => $customer->default_language,
                'total_conversations' => $customer->total_conversations,
                'total_tickets' => $customer->total_tickets,
                'satisfaction_score' => $customer->satisfaction_score,
                'last_contacted_at' => $customer->last_contacted_at?->toISOString(),
                'created_at' => $customer->created_at->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to build customer context:', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
            ]);

            return [];
        }
    }

    /**
     * Build minimal customer context
     */
    public function buildMinimal(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->full_name,
            'email' => $customer->email,
        ];
    }
}