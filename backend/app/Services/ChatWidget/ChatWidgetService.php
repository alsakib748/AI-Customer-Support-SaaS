<?php
// app/Services/ChatWidget/ChatWidgetService.php

namespace App\Services\ChatWidget;

use App\Models\Tenant\ChatWidget;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\Tenant\WidgetSession;
use App\Services\Conversation\ConversationService;
use App\Services\Message\MessageService;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChatWidgetService
{
    protected ConversationService $conversationService;
    protected MessageService $messageService;
    protected CustomerService $customerService;

    public function __construct(
        ConversationService $conversationService,
        MessageService $messageService,
        CustomerService $customerService
    ) {
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
        $this->customerService = $customerService;
    }

    /**
     * Bootstrap the widget
     */
    public function bootstrap(string $publicKey, string $origin): array
    {
        $widget = ChatWidget::active()
            ->byPublicKey($publicKey)
            ->first();

        if (!$widget) {
            throw ValidationException::withMessages([
                'widget' => ['Widget not found or inactive.'],
            ]);
        }

        // Validate origin
        if (!$widget->isOriginAllowed($origin)) {
            throw ValidationException::withMessages([
                'origin' => ['This domain is not allowed for this widget.'],
            ]);
        }

        return [
            'widget' => $this->formatWidgetConfig($widget),
            'features' => [
                'attachments' => false,
                'voice' => false,
                'ai' => false,
                'realtime' => false,
            ],
        ];
    }

    /**
     * Create or get widget session
     */
    public function getOrCreateSession(ChatWidget $widget, ?string $visitorToken = null): WidgetSession
    {
        // Try to find existing session by visitor token
        if ($visitorToken) {
            $session = WidgetSession::byVisitorToken($visitorToken)
                ->where('widget_id', $widget->id)
                ->first();

            if ($session) {
                $session->updateLastSeen();
                return $session;
            }
        }

        // Create new session
        $session = WidgetSession::create([
            'widget_id' => $widget->id,
            'last_seen_at' => now(),
        ]);

        Log::info('Widget session created', [
            'widget_id' => $widget->id,
            'session_token' => $session->session_token,
        ]);

        return $session;
    }

    /**
     * Validate widget session
     */
    public function validateSession(string $sessionToken): WidgetSession
    {
        $session = WidgetSession::bySessionToken($sessionToken)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages([
                'session' => ['Invalid session.'],
            ]);
        }

        // Check widget is active
        if (!$session->widget->isActive()) {
            throw ValidationException::withMessages([
                'widget' => ['Widget is not active.'],
            ]);
        }

        $session->updateLastSeen();

        return $session;
    }

    /**
     * Get or create customer for session
     */
    public function getOrCreateCustomer(WidgetSession $session, array $data): Customer
    {
        if ($session->hasCustomer()) {
            return $session->customer;
        }

        // Validate customer data
        $widget = $session->widget;

        if ($widget->require_name && empty($data['name'])) {
            throw ValidationException::withMessages([
                'name' => ['Name is required.'],
            ]);
        }

        if ($widget->require_email && empty($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['Email is required.'],
            ]);
        }

        if ($widget->require_phone && empty($data['phone'])) {
            throw ValidationException::withMessages([
                'phone' => ['Phone is required.'],
            ]);
        }

        // Try to find existing customer by email
        $customer = null;
        if (!empty($data['email'])) {
            $customer = Customer::where('email', $data['email'])->first();
        }

        if (!$customer) {
            // Create new customer
            $customer = $this->customerService->createCustomer([
                'first_name' => $data['name'] ?? 'Anonymous',
                'last_name' => '',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
                'tags' => ['widget'],
                'metadata' => [
                    'source' => 'widget',
                    'widget_id' => $widget->id,
                    'session_id' => $session->id,
                ],
            ]);

            Log::info('Customer created from widget', [
                'customer_id' => $customer->id,
                'widget_id' => $widget->id,
            ]);
        }

        // Associate with session
        $session->associateCustomer($customer);

        return $customer;
    }

    /**
     * Get or create active conversation for customer
     */
    public function getOrCreateConversation(Customer $customer, string $channel = 'web'): Conversation
    {
        // Check for existing active conversation
        $conversation = $this->conversationService->getActiveConversationForCustomer($customer->id, $channel);

        if ($conversation) {
            return $conversation;
        }

        // Create new conversation
        $conversation = $this->conversationService->create([
            'customer_id' => $customer->id,
            'subject' => 'New Conversation',
            'channel' => $channel,
            'priority' => 'normal',
        ]);

        Log::info('Conversation created from widget', [
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
        ]);

        return $conversation;
    }

    /**
     * Send a message from the widget
     */
    public function sendMessage(WidgetSession $session, string $content): Message
    {
        // Ensure we have a customer
        $customer = $session->customer;
        if (!$customer) {
            throw ValidationException::withMessages([
                'customer' => ['Customer not found. Please provide customer information.'],
            ]);
        }

        // Ensure we have a conversation
        $conversation = $session->currentConversation;
        if (!$conversation) {
            // Create new conversation
            $conversation = $this->getOrCreateConversation($customer);
            $session->associateConversation($conversation);
        }

        // Check if conversation is resolved or closed
        if ($conversation->isResolved() || $conversation->isClosed()) {
            // Create new conversation for new messages after resolution
            $conversation = $this->getOrCreateConversation($customer);
            $session->associateConversation($conversation);
        }

        // Create message
        $message = $this->messageService->createCustomerMessage(
            $conversation,
            $customer,
            ['content' => $content]
        );

        Log::info('Message sent from widget', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
        ]);

        return $message;
    }

    /**
     * Get messages for widget
     */
    public function getMessages(WidgetSession $session, int $limit = 50): array
    {
        $conversation = $session->currentConversation;

        if (!$conversation) {
            return [];
        }

        $messages = $this->messageService->getMessages(
            $conversation,
            ['per_page' => $limit]
        );

        return $messages->items();
    }

    /**
     * Get conversation for widget
     */
    public function getConversation(WidgetSession $session): ?Conversation
    {
        return $session->currentConversation;
    }

    /**
     * Format widget configuration for frontend
     */
    protected function formatWidgetConfig(ChatWidget $widget): array
    {
        return [
            'id' => $widget->id,
            'public_key' => $widget->public_key,
            'name' => $widget->name,
            'header_title' => $widget->header_title ?? 'Chat with us',
            'welcome_message' => $widget->welcome_message,
            'offline_message' => $widget->offline_message,
            'position' => $widget->position ?? 'bottom-right',
            'primary_color' => $widget->primary_color,
            'logo' => $widget->logo,
            'avatar' => $widget->avatar,
            'show_branding' => $widget->show_branding,
            'require_name' => $widget->require_name,
            'require_email' => $widget->require_email,
            'require_phone' => $widget->require_phone,
        ];
    }
}
