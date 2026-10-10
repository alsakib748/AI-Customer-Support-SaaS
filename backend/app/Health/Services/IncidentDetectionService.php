<?php

namespace App\Health\Services;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\IncidentSeverity;
use App\Health\Enums\IncidentStatus;
use App\Health\Support\HealthConfig;
use App\Models\PlatformIncident;
use App\Models\PlatformIncidentEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Converts recurring check outcomes into platform incidents. Uses consecutive-
 * confirmation counters to avoid alert fatigue, and dedupes against the active
 * (open/acknowledged) incident for the same fingerprint.
 */
class IncidentDetectionService
{
    protected const FAILURE_COUNTER = 'health:counter:';
    protected const RECOVERY_COUNTER = 'health:recovery:';

    /**
     * @param HealthCheckResult[] $results
     */
    public function evaluate(array $results, string $source = 'scheduled', ?int $actorId = null): void
    {
        foreach ($results as $result) {
            $this->evaluateResult($result, $source, $actorId);
        }
    }

    public function acknowledge(PlatformIncident $incident, ?int $actorId = null): PlatformIncident
    {
        if (! $incident->status->isActive()) {
            throw new \InvalidArgumentException('Only active incidents can be acknowledged.');
        }

        $incident->update([
            'status'          => IncidentStatus::Acknowledged->value,
            'acknowledged_at' => Carbon::now(),
            'acknowledged_by' => $actorId,
        ]);

        $this->recordEvent($incident, PlatformIncidentEvent::EVENT_ACKNOWLEDGED, $actorId);

        return $incident->refresh();
    }

    public function resolve(PlatformIncident $incident, ?int $actorId = null): PlatformIncident
    {
        if ($incident->status === IncidentStatus::Resolved) {
            return $incident;
        }

        $incident->update([
            'status'       => IncidentStatus::Resolved->value,
            'resolved_at'  => Carbon::now(),
            'resolved_by'  => $actorId,
        ]);

        $this->recordEvent($incident, PlatformIncidentEvent::EVENT_RESOLVED, $actorId);

        return $incident->refresh();
    }

    protected function evaluateResult(HealthCheckResult $result, string $source, ?int $actorId): void
    {
        $status = $result->status;

        if ($status->isDisabled() || $status->isUnknown()) {
            return;
        }

        // Incidents open on hard failure AND on sustained degradation; they only
        // recover once a check is reported fully healthy.
        $failed = $status->isFailure() || $status->isDegraded();
        $failureConfirms = (int) config('health.incidents.failure_confirmation', 2);
        $recoveryConfirms = (int) config('health.incidents.recovery_confirmation', 2);

        if (! $failed) {
            Cache::forget(self::FAILURE_COUNTER.$result->key);
            $recoveries = (int) Cache::get(self::RECOVERY_COUNTER.$result->key, 0) + 1;
            Cache::put(self::RECOVERY_COUNTER.$result->key, $recoveries, 3600);

            if ($recoveries >= $recoveryConfirms) {
                $this->autoResolve($result->key, $source);
            }

            return;
        }

        Cache::forget(self::RECOVERY_COUNTER.$result->key);
        $failures = (int) Cache::get(self::FAILURE_COUNTER.$result->key, 0) + 1;
        Cache::put(self::FAILURE_COUNTER.$result->key, $failures, 3600);

        if ($failures < $failureConfirms) {
            return;
        }

        $this->raiseOrUpdate($result, $source);
    }

    protected function raiseOrUpdate(HealthCheckResult $result, string $source): void
    {
        $fingerprint = $this->fingerprint($result->key);
        $critical = (bool) HealthConfig::check($result->key, 'critical', false);
        $severity = $this->severityFor($result, $critical);
        $title = $this->titleFor($result->key, $result->status);
        $summary = $result->summary ?? ($result->status->label().' for '.$this->labelFor($result->key));

        $incident = PlatformIncident::query()
            ->forFingerprint($fingerprint)
            ->active()
            ->first();

        if ($incident === null) {
            $incident = PlatformIncident::create([
                'fingerprint' => $fingerprint,
                'title'       => $title,
                'summary'     => $summary,
                'severity'    => $severity->value,
                'status'      => IncidentStatus::Open->value,
                'source'      => $source,
                'started_at'  => Carbon::now(),
                'metadata'    => [
                    'check_key'   => $result->key,
                    'status'      => $result->status->value,
                    'duration_ms' => $result->durationMs,
                ],
            ]);

            $this->recordEvent($incident, PlatformIncidentEvent::EVENT_OPENED, null);
            return;
        }

        $changed = $incident->severity !== $severity || $incident->summary !== $summary;

        $incident->update([
            'title'    => $title,
            'summary'  => $summary,
            'severity' => $severity->value,
        ]);

        if ($changed && $this->cooldownPassed($incident)) {
            $this->recordEvent($incident, PlatformIncidentEvent::EVENT_UPDATED, null);
        }
    }

    protected function autoResolve(string $checkKey, string $source): void
    {
        $incident = PlatformIncident::query()
            ->forFingerprint($this->fingerprint($checkKey))
            ->active()
            ->first();

        if ($incident === null) {
            return;
        }

        $this->resolve($incident, null);
    }

    protected function cooldownPassed(PlatformIncident $incident): bool
    {
        $cooldown = (int) config('health.incidents.cooldown_seconds', 300);
        $last = $incident->events()->orderByDesc('id')->first();

        return $last === null || abs(now()->diffInSeconds($last->created_at)) >= $cooldown;
    }

    protected function recordEvent(PlatformIncident $incident, string $event, ?int $actorId): void
    {
        PlatformIncidentEvent::create([
            'incident_id' => $incident->id,
            'event'       => $event,
            'severity'    => $incident->severity->value,
            'summary'     => $incident->summary,
            'actor_id'    => $actorId,
        ]);
    }

    protected function severityFor(HealthCheckResult $result, bool $critical): IncidentSeverity
    {
        return match (true) {
            $result->status->isFailure()  => $critical ? IncidentSeverity::Critical : IncidentSeverity::Error,
            $result->status->isDegraded() => $critical ? IncidentSeverity::Error : IncidentSeverity::Warning,
            default                       => IncidentSeverity::Warning,
        };
    }

    protected function titleFor(string $key, \App\Health\Enums\HealthStatus $status): string
    {
        return $this->labelFor($key).' '.strtolower(($status === \App\Health\Enums\HealthStatus::Unhealthy ? 'outage detected' : 'performance degraded'));
    }

    protected function labelFor(string $key): string
    {
        return (string) HealthConfig::check($key, 'label', $key);
    }

    protected function fingerprint(string $checkKey): string
    {
        return 'health:'.$checkKey;
    }
}