<?php

namespace App\Jobs\Billing;

use App\Models\PaymentWebhookEvent;
use App\Services\Billing\BillingWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBillingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $webhookEventId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BillingWebhookService $processor): void
    {
        \Log::info('ProcessBillingWebhookJob START', [
        'webhook_event_id' => $this->webhookEventId,
    ]);
        $record = PaymentWebhookEvent::find($this->webhookEventId);

        if (!$record) {
        \Log::warning('ProcessBillingWebhookJob: record not found', [
            'id' => $this->webhookEventId,
        ]);
        return;
    }

    \Log::info('ProcessBillingWebhookJob: record found', [
        'id'       => $record->id,
        'provider' => $record->provider,
        'type'     => $record->event_type,
        'status'   => $record->status,
        'has_normalized' => !empty($record->normalized_payload),
    ]);

        if ($record->isProcessed()) {
            Log::info('Webhook already processed — skipping', ['id' => $record->id]);
            return;
        }

        try {
            $normalized = $record->normalized_payload ?? [];

            if (empty($normalized)) {
                $record->markAsIgnored('Empty normalized payload');
                return;
            }

            $processor->process($normalized);

            \Log::info('ProcessBillingWebhookJob: service returned, marking processed');

            $record->markAsProcessed();

            \Log::info('ProcessBillingWebhookJob: DONE');
        } catch (\Throwable $e) {
            $record->markAsFailed($e->getMessage());
            Log::error('Webhook processing failed', [
                'webhook_id' => $record->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
