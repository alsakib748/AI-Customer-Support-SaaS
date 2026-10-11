<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $connection = 'central';
    protected $table      = 'payment_webhook_events';

    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'status',
        'attempts',
        'payload',
        'normalized_payload',
        'error_message',
        'last_error',
        'processed_at',
        'received_at',
    ];

    protected $casts = [
        'payload'            => 'array',
        'normalized_payload' => 'array',
        'attempts'           => 'integer',
        'processed_at'       => 'datetime',
        'received_at'        => 'datetime',
    ];

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Atomically claim a received event for processing. Returns true only for
     * the single caller that transitions it out of the "received" state, so
     * concurrent deliveries cannot both enqueue work.
     */
    public function claimForProcessing(): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->where('status', 'received')
            ->update(['status' => 'processing']) === 1;
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status'       => 'processed',
            'processed_at' => now(),
            'error_message'=> null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
            'last_error'    => $error,
            'attempts'      => ($this->attempts ?? 0) + 1,
        ]);
    }

    public function markAsIgnored(?string $reason = null): void
    {
        $this->update([
            'status'        => 'ignored',
            'processed_at'  => now(),
            'error_message' => $reason,
        ]);
    }
}
