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
        $this->tenant = $this->resolveTenant();
        $this->user = auth()->user();
    }

    /**
     * Resolve the active tenant from tenancy or the request-bound current
     * tenant, without throwing when neither is available.
     */
    protected function resolveTenant()
    {
        try {
            if (function_exists('tenancy') && tenancy()->tenant) {
                return tenancy()->tenant;
            }
        } catch (\Throwable $e) {
            // Tenancy is not initialized; fall through to the bound instance.
        }

        try {
            return app()->bound('current_tenant') ? app('current_tenant') : null;
        } catch (\Throwable $e) {
            return null;
        }
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
     * Whether this tool may only run when a server-side conversation context
     * has been established.
     */
    protected function requiresConversationContext(): bool
    {
        return false;
    }

    /**
     * Check if tool is authorized.
     *
     * Tools only run inside an active tenant context, and context-sensitive
     * tools additionally require a conversation context that the server set
     * (never one supplied by the model).
     */
    protected function isAuthorized(): bool
    {
        if (! $this->tenant) {
            return false;
        }

        if ($this->requiresConversationContext() && ! $this->conversation) {
            return false;
        }

        return true;
    }

    /**
     * The customer associated with the current conversation context.
     */
    protected function contextCustomer()
    {
        if ($this->customer) {
            return $this->customer;
        }

        if ($this->conversation) {
            return $this->conversation->customer;
        }

        return null;
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
