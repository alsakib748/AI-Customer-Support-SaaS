<?php

namespace App\Listeners\Billing;

use App\Events\Billing\InvoicePaid;
use App\Notifications\Billing\InvoicePaidNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendInvoicePaidNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoicePaid $event): void
    {
        try {
            $owner = $event->invoice->tenant->getOwner();

            if ($owner) {
                $owner->notify(new InvoicePaidNotification($event->invoice));
            }

            Log::info('Invoice paid notification sent', [
                'invoice_id' => $event->invoice->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send invoice paid notification', [
                'invoice_id' => $event->invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}