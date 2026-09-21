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
        'payload',
        'normalized_payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload'            => 'array',
        'normalized_payload' => 'array',
        'processed_at'       => 'datetime',
    ];

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
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
