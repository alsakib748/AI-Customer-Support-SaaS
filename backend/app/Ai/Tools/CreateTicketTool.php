<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Customer;
use App\Services\Ticket\TicketService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTicketTool extends BaseTool
{
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
    protected TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        parent::__construct();
        $this->ticketService = $ticketService;
    }

    public function name(): string
    {
        return 'create_ticket';
    }

    public function description(): string
    {
        return 'Create a support ticket for a customer issue.';
    }

    public function parameters(): array
    {
        return [
            'customer_id' => [
                'type' => 'integer',
                'description' => 'Customer ID',
                'required' => true,
            ],
            'subject' => [
                'type' => 'string',
                'description' => 'Ticket subject',
                'required' => true,
            ],
            'description' => [
                'type' => 'string',
                'description' => 'Detailed description of the issue',
                'required' => true,
            ],
            'priority' => [
                'type' => 'string',
                'description' => 'Priority: low, normal, high, urgent',
                'enum' => ['low', 'normal', 'high', 'urgent'],
                'default' => 'normal',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Ticket type: general, technical, billing, account, bug, feature_request, other',
                'enum' => ['general', 'technical', 'billing', 'account', 'bug', 'feature_request', 'other'],
                'default' => 'general',
            ],
        ];
    }

    public function execute(array $parameters): array
    {
        try {
            if (!$this->isAuthorized()) {
                return $this->error('Unauthorized to create tickets.');
            }

            // Validate required parameters
            $customerId = $parameters['customer_id'] ?? null;
            $subject = $parameters['subject'] ?? null;
            $description = $parameters['description'] ?? null;

            if (!$customerId) {
                return $this->error('Customer ID is required.');
            }

            if (!$subject) {
                return $this->error('Subject is required.');
            }

            if (!$description) {
                return $this->error('Description is required.');
            }

            // Verify customer exists and belongs to tenant
            $customer = Customer::find($customerId);
            if (!$customer) {
                return $this->error('Customer not found.');
            }

            // Check if customer belongs to current tenant (handled by tenant DB)

            // Create ticket data
            $ticketData = [
                'customer_id' => $customerId,
                'conversation_id' => $this->conversation?->id,
                'subject' => $subject,
                'description' => $description,
                'priority' => $parameters['priority'] ?? 'normal',
                'type' => $parameters['type'] ?? 'general',
                'source' => 'ai',
            ];

            // Create ticket
            $ticket = $this->ticketService->createTicket($ticketData);

            // Create system message in conversation
            if ($this->conversation) {
                $systemMessage = "A ticket has been created for this issue: TKT-" . $ticket->id;
                // Could create a system message here
            }

            $this->logExecution('create_ticket', $parameters, [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);

            return [
                'success' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'type' => $ticket->type,
                    'created_at' => $ticket->created_at->toISOString(),
                ],
                'message' => 'Ticket created successfully.',
            ];

        } catch (ValidationException $e) {
            Log::error('Ticket creation validation failed:', ['errors' => $e->errors()]);
            return $this->error('Validation failed: ' . json_encode($e->errors()));
        } catch (\Exception $e) {
            Log::error('Ticket creation failed:', ['error' => $e->getMessage()]);
            return $this->error('Failed to create ticket: ' . $e->getMessage());
        }
    }

    protected function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'ticket' => null,
        ];
    }
}
