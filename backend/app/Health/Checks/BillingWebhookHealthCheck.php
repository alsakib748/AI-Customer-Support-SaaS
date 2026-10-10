<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Carbon;

/**
 * Surfaces signs that billing webhook processing has stalled. It reads the
 * central webhook event ledger (never probes provider endpoints, and never
 * exposes payload contents in metadata).
 */
class BillingWebhookHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'billing.webhooks';
    }

    protected function perform(): HealthCheckResult
    {
        $received = (int) PaymentWebhookEvent::query()->where('status', 'received')->count();
        $failed = (int) PaymentWebhookEvent::query()->where('status', 'failed')->count();

        $oldestRaw = PaymentWebhookEvent::query()
            ->where('status', 'received')
            ->orderBy('created_at')
            ->value('created_at');

        $oldestMinutes = $oldestRaw ? (int) max(0, abs(Carbon::parse($oldestRaw)->diffInMinutes(Carbon::now()))) : 0;

        $metadata = [
            'received_pending'       => $received,
            'oldest_age_minutes'     => $oldestMinutes,
            'failed'                 => $failed,
        ];

        $degradedPending = (int) $this->config('degraded_pending', 5);
        $unhealthyPending = (int) $this->config('unhealthy_pending', 100);
        $degradedOldest = (int) $this->config('degraded_oldest_minutes', 15);
        $unhealthyOldest = (int) $this->config('unhealthy_oldest_minutes', 120);
        $degradedFailed = (int) $this->config('degraded_failed', 1);
        $unhealthyFailed = (int) $this->config('unhealthy_failed', 25);

        if (
            $failed >= $unhealthyFailed
            || $received >= $unhealthyPending
            || $oldestMinutes >= $unhealthyOldest
        ) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, 'Billing webhook processing backlog', $metadata);
        }

        if (
            $failed >= $degradedFailed
            || $received >= $degradedPending
            || $oldestMinutes >= $degradedOldest
        ) {
            return HealthCheckResult::make($this->key(), HealthStatus::Degraded, 'Billing webhook processing showing pressure', $metadata);
        }

        return HealthCheckResult::make($this->key(), HealthStatus::Healthy, 'Billing webhooks processing normally', $metadata);
    }
}