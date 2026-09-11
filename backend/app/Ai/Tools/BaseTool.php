<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

abstract class BaseTool
{
    protected $tenant;
    protected $conversation;
    protected $customer;
    protected $user;

    public function __construct()
    {
        $this->tenant = app('current_tenant');
        $this->user = auth()->user();
    }

    /**
     * Set conversation context
     */
    public function setConversation($conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    /**
     * Set customer context
     */
    public function setCustomer($customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * Get tool name
     */
    abstract public function name(): string;

    /**
     * Get tool description
     */
    abstract public function description(): string;

    /**
     * Execute the tool
     */
    abstract public function execute(array $parameters): array;

    /**
     * Get tool parameters schema
     */
    abstract public function parameters(): array;

    /**
     * Check if tool is authorized
     */
    protected function isAuthorized(): bool
    {
        return true;
    }

    /**
     * Log tool execution
     */
    protected function logExecution(string $tool, array $parameters, $result): void
    {
        Log::info('AI Tool executed', [
            'tool' => $tool,
            'parameters' => $parameters,
            'tenant_id' => $this->tenant?->id,
            'conversation_id' => $this->conversation?->id,
            'user_id' => $this->user?->id,
        ]);
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