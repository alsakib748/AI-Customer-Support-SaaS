<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Mail is configuration-gated: if the app uses a log driver no external mail
 * is expected to flow, so the check is reported as disabled rather than fake
 * healthy. A last-success timestamp (written by the settings "send test email"
 * action) is surfaced when present.
 */
class MailHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'mail.default';
    }

    protected function perform(): HealthCheckResult
    {
        $driver = (string) config('mail.default');
        $host = (string) config('mail.mailers.'.$driver.'.host', '');
        $from = (string) config('mail.from.address', '');

        $metadata = ['driver' => $driver];

        if (in_array($driver, ['log', 'array', 'sendmail'], true)) {
            return HealthCheckResult::make($this->key(), HealthStatus::Disabled, "Mail driver '{$driver}' - external mail disabled", $metadata);
        }

        $lastSuccess = Cache::get(config('health.heartbeat.mail_last_success_key', 'health:mail:last_success'));
        if ($lastSuccess !== null) {
            $metadata['last_success_at'] = Cache::get(config('health.heartbeat.mail_last_success_at_key', 'health:mail:last_success_at'));
        }

        if ($driver === 'smtp' && $host === '') {
            return HealthCheckResult::make($this->key(), HealthStatus::Degraded, 'SMTP host not configured', $metadata);
        }

        if ($from === '') {
            return HealthCheckResult::make($this->key(), HealthStatus::Degraded, 'No from-address configured', $metadata);
        }

        return HealthCheckResult::make($this->key(), HealthStatus::Healthy, "Mail driver '{$driver}' configured", $metadata);
    }
}