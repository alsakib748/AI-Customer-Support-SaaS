<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\Billing\ProcessPaymentWebhook;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {
    }

    public function handle(Request $request, string $provider)
    {
        $payload = $request->getContent();
        $signature = $this->extractSignature($request, $provider);

        try {
            $gateway = $this->gateways->gateway($provider);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Unknown provider.'], 404);
        }

        if (!$gateway->verifyWebhook($payload, $signature)) {
            Log::warning('Webhook signature verification failed', ['provider' => $provider]);
            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true) ?: [];
        $eventId = $this->extractEventId($event, $provider);
        $eventType = $this->extractEventType($event, $provider);

        // IDEMPOTENCY: unique (provider, event_id)
        $record = PaymentWebhookEvent::firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            [
                'event_type' => $eventType,
                'status' => 'received',
                'payload' => $event,
            ]
        );

        if (!$record->wasRecentlyCreated && $record->processed_at) {
            Log::info('Webhook already processed — ignoring', [
                'provider' => $provider,
                'event_id' => $eventId,
            ]);
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        // Dispatch for async processing
        \App\Jobs\Billing\ProcessPaymentWebhook::dispatch($provider, $event, $record->id);

        return response()->json(['received' => true]);
    }

    protected function extractSignature(Request $request, string $provider): string
    {
        return match ($provider) {
            'stripe' => (string) $request->header('Stripe-Signature', ''),
            'paypal' => (string) $request->header('PAYPAL-TRANSMISSION-SIG', ''),
            default => '',
        };
    }

    protected function extractEventId(array $event, string $provider): string
    {
        return match ($provider) {
            'stripe' => (string) ($event['id'] ?? ''),
            'paypal' => (string) ($event['id'] ?? $event['resource']['id'] ?? ''),
            default => (string) ($event['id'] ?? uniqid('evt_', true)),
        };
    }

    protected function extractEventType(array $event, string $provider): string
    {
        return match ($provider) {
            'stripe' => (string) ($event['type'] ?? 'unknown'),
            'paypal' => (string) ($event['event_type'] ?? 'unknown'),
            default => 'unknown',
        };
    }

    /**
     * Handle Stripe webhook
     */
    // public function stripe(Request $request)
    // {
    //     $payload = $request->getContent();
    //     $signature = $request->header('Stripe-Signature');

    //     if (!$this->stripeService->verifyWebhook($payload, $signature)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid signature.',
    //         ], 400);
    //     }

    //     $event = json_decode($payload, true);

    //     // Process async
    //     ProcessPaymentWebhook::dispatch('stripe', $event);

    //     return response()->json(['received' => true]);
    // }

    /**
     * Handle PayPal webhook
     */
    // public function paypal(Request $request)
    // {
    //     $payload = $request->all();

    //     // Process async
    //     ProcessPaymentWebhook::dispatch('paypal', $payload);

    //     return response()->json(['received' => true]);
    // }
}