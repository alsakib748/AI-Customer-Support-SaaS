<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

class BillingReminder extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'type',
        'days_before',
        'period_key',
        'status',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Atomically claim the right to send a reminder.
     *
     * Relies on the unique index so two concurrent runs cannot both send the
     * same reminder. Returns true only for the caller that inserted the row.
     */
    public static function claim(
        string $tenantId,
        int $subscriptionId,
        string $type,
        int $daysBefore,
        string $periodKey
    ): bool {
        try {
            static::create([
                'tenant_id'       => $tenantId,
                'subscription_id' => $subscriptionId,
                'type'            => $type,
                'days_before'     => $daysBefore,
                'period_key'      => $periodKey,
                'status'          => 'sent',
                'sent_at'         => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException $e) {
            return false;
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '23505'], true)) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Release a claim so the reminder can be retried after a delivery failure.
     */
    public static function release(
        int $subscriptionId,
        string $type,
        int $daysBefore,
        string $periodKey
    ): void {
        static::where('subscription_id', $subscriptionId)
            ->where('type', $type)
            ->where('days_before', $daysBefore)
            ->where('period_key', $periodKey)
            ->delete();
    }
}
