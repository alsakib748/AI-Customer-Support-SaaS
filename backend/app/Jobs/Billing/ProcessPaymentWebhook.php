<?php

namespace App\Jobs\Billing;

use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public ?int $recordId = null,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $gateways): void
    {
        $record = $this->recordId ? PaymentWebhookEvent::find($this->recordId) : null;

        try {
            $gateways->gateway($this->provider)->handleWebhook($this->payload);

            $record?->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $record?->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Webhook processing failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // protected function processStripeWebhook(): void
    // {
    //     $type = $this->payload['type'] ?? null;

    //     match ($type) {
    //         'invoice.paid' => $this->handleInvoicePaid(),
    //         'invoice.payment_failed' => $this->handlePaymentFailed(),
    //         'customer.subscription.deleted' => $this->handleSubscriptionDeleted(),
    //         'customer.subscription.updated' => $this->handleSubscriptionUpdated(),
    //         default => null,
    //     };
    // }

    // protected function processPaypalWebhook(): void
    // {
    //     // Implement PayPal webhook handling
    // }

    // protected function handleInvoicePaid(): void
    // {
    //     // Implementation
    // }

    // protected function handlePaymentFailed(): void
    // {
    //     // Implementation
    // }

    // protected function handleSubscriptionDeleted(): void
    // {
    //     // Implementation
    // }

    // protected function handleSubscriptionUpdated(): void
    // {
    //     // Implementation
    // }

}