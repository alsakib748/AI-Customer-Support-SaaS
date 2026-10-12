<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetCustomerTool extends BaseTool
{
    public function name(): string
    {
        return 'get_customer';
    }

    public function description(): string
    {
        return 'Get customer information by ID or email.';
    }

    public function parameters(): array
    {
        return [
            'customer_id' => [
                'type' => 'integer',
                'description' => 'Customer ID',
                'required' => false,
            ],
            'email' => [
                'type' => 'string',
                'description' => 'Customer email address',
                'required' => false,
            ],
        ];
    }

    protected function requiresConversationContext(): bool
    {
        return true;
    }

    public function execute(array $parameters): array
    {
        try {
            if (! $this->isAuthorized()) {
                return $this->error('Unauthorized to access customer information.');
            }

            $customer = $this->contextCustomer();

            if (! $customer) {
                return $this->error('Customer not found.');
            }

            // Any identifier the model supplies must match the customer of the
            // conversation being served; tools may not enumerate other customers.
            $customerId = $parameters['customer_id'] ?? null;
            $email = $parameters['email'] ?? null;

            if ($customerId !== null && (int) $customerId !== (int) $customer->id) {
                return $this->error('Access denied for the requested customer.');
            }

            if ($email !== null && strcasecmp((string) $email, (string) $customer->email) !== 0) {
                return $this->error('Access denied for the requested customer.');
            }

            $result = [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company' => $customer->company_name,
                'status' => $customer->status,
                'total_conversations' => $customer->total_conversations,
                'total_tickets' => $customer->total_tickets,
                'satisfaction_score' => $customer->satisfaction_score,
                'created_at' => $customer->created_at->toISOString(),
            ];

            $this->logExecution('get_customer', $parameters, $result);

            return [
                'success' => true,
                'customer' => $result,
                'message' => 'Customer retrieved successfully.',
            ];

        } catch (\Exception $e) {
            Log::error('Get customer failed:', ['error' => $e->getMessage()]);

            return $this->error('Failed to get customer.');
        }
    }

    protected function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'customer' => null,
        ];
    }

    // /**
    //  * Get the description of the tool's purpose.
    //  */
    // public function description(): Stringable|string
    // {
    //     return 'A description of the tool.';
    // }

    // /**
    //  * Execute the tool.
    //  */
    // public function handle(Request $request): Stringable|string
    // {
    //     //
    // }

    // /**
    //  * Get the tool's schema definition.
    //  */
    // public function schema(JsonSchema $schema): array
    // {
    //     return [
    //         'value' => $schema->string()->required(),
    //     ];
    // }
}
