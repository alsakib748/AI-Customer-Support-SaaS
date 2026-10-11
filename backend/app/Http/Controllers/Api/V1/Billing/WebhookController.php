<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\Billing\ProcessBillingWebhookJob;
use App\Models\PaymentWebhookEvent;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {
    }

    public function handle(Request $request, string $provider)
    {
        if (!$this->gateways->has($provider)) {
            return response()->json(['error' => 'Unsupported provider'], 404);
        }

        $payload = $request->getContent();
        $headers = $request->headers->all();
        $gateway = $this->gateways->driver($provider);

        if (!$gateway->verifyWebhook($payload, $headers)) {
            Log::warning('Webhook signature verification failed', ['provider' => $provider]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $gateway->parseWebhook($payload, $headers);

        // Idempotency: a single row per provider event.
        $record = PaymentWebhookEvent::firstOrCreate(
            [
                'provider'          => $provider,
                'provider_event_id' => $event['provider_event_id'],
            ],
            [
                'event_type'         => $event['type'],
                'status'             => 'received',
                'payload'            => $event['raw'],
                'normalized_payload' => $event,
                'received_at'        => now(),
            ]
        );

        // Atomically claim the event so concurrent deliveries cannot both enqueue
        // work. Only the caller that flips "received" -> "processing" dispatches.
        if ($record->claimForProcessing()) {
            ProcessBillingWebhookJob::dispatch($record->id);
        } else {
            Log::info('Duplicate webhook ignored', [
                'provider' => $provider,
                'event_id' => $event['provider_event_id'],
                'status'   => $record->status,
            ]);
        }

        // Acknowledge quickly
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
}
