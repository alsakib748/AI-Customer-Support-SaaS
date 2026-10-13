# Monitoring, health checks and alerting

Phase 6, Workstream 4 (WS4). This document describes the monitoring surface that
exists **in the repository** and the operational steps that must be performed on
real infrastructure (which are **not executed** here).

---

## 1. What exists in the repository

### Health checks

- Registry and classes live in `backend/app/Health/`.
- Every check is declared (label, criticality, thresholds) in
  `backend/config/health.php`; checks are run by `HealthCheckRunner` and store
  results through `HealthCheckStorage`.
- No check hardcodes `Healthy`. A check that cannot prove health returns
  `Unknown`/`Disabled`, never a stale `Healthy`.
- A stored result older than `HEALTH_STALE_AFTER_SECONDS` (default 600s) is shown
  as `unknown` on the dashboard (`HealthSnapshotService::resolveStatus`). A
  stale `Disabled` result stays `Disabled`.

### Scheduled jobs (`backend/routes/console.php`)

| Command | Purpose | Cadence |
| --- | --- | --- |
| `health:check` | run all enabled checks, store results, evaluate incidents | every minute |
| `health:heartbeat` | record scheduler/worker heartbeats | every minute |
| `health:prune` | prune old results per `health.*` retention | daily |

### Incidents

- `IncidentDetectionService` uses consecutive-failure confirmation
  (`HEALTH_INCIDENT_FAILURE_CONFIRMATIONS`, default 2) and a cooldown before it
  opens a `PlatformIncident`, and the same confirmation count before it
  auto-resolves.
- Incidents dispatch `PlatformIncidentOpened` / `PlatformIncidentResolved`,
  handled by `NotifyPlatformIncidentOpened` / `NotifyPlatformIncidentResolved`
  (in-app platform notifications to super admins).

### AI provider probe (WS1/WS4)

- `AiProviderHealthCheck` performs a bounded, read-only probe only when
  `HEALTH_AI_PROBE=true`. The production template sets `HEALTH_AI_PROBE=false`
  so the live probe is **off by default** and checks report `Unknown`.
- Provider API keys are sent in headers (e.g. `x-goog-api-key` for Gemini,
  `Authorization: Bearer` for OpenAI) and never in the URL. Stored metadata URLs
  are additionally redacted (`AiProviderHealthCheck::redactUrl`) so any
  credential-shaped query parameter is masked before persistence.

---

## 2. Configuration

| Variable | Purpose | Production guidance |
| --- | --- | --- |
| `HEALTH_MONITORING_ENABLED` | master switch | `true` |
| `HEALTH_STALE_AFTER_SECONDS` | stale threshold | `600` (>= 2x check interval) |
| `HEALTH_AI_PROBE` | live AI provider probe | `false` unless egress + budget are intended |
| `HEALTH_AI_PROBE_TIMEOUT` | probe timeout (s) | `5` |
| `HEALTH_INCIDENT_FAILURE_CONFIRMATIONS` | failures before opening | `2`+ |
| `HEALTH_INCIDENT_RECOVERY_CONFIRMATIONS` | healthy observations before auto-resolving | `2`+ |
| `HEALTH_INCIDENT_COOLDOWN` | seconds between duplicate incident updates | `300`+ |

---

## 3. How to verify in staging (NOT executed here)

Run on a staging host with the candidate release:

```bash
php artisan health:check --verbose
php artisan health:heartbeat
php artisan tinker --execute="dump(app(App\\Health\\Services\\HealthSnapshotService::class)->get(true));"
```

Evidence to capture:

- `health:check` exit code and per-check rows (status, summary, duration).
- A snapshot response showing non-`healthy` when a dependency is stopped and
  `healthy` when it is restored.
- `PlatformIncident` opened then auto-resolved after the dependency recovers.

### Alert delivery verification (external channel)

The repository ships **in-app platform notifications only**. There is no
Slack/PagerDuty/email alert channel wired in code. To verify alert delivery:

1. Add or configure an external channel (Slack webhook, mail channel, or an
   APM/PagerDuty integration) and route `PlatformIncidentOpened` to it.
2. Force a failing check in staging and confirm the alert arrives in the channel
   with the incident fingerprint and severity.
3. Resolve the dependency and confirm the resolved notification arrives.

Evidence: channel screenshot/timestamp with the incident id and fingerprint.

> **Status: NOT executed.** No channel credentials or host access are available
> from this repository. This is an operational task.

---

## 4. Regression coverage (in-repo)

- `tests/Feature/Health/*` — runner, snapshot staleness/freshness, incidents,
  queue check.
- `tests/Unit/Health/*` — status evaluation.
- `tests/Feature/Ops/HealthSnapshotFreshnessTest.php` — snapshot cache vs
  `fresh=true`.
- `tests/Unit/Ops/HealthAiProbeRedactionTest.php` — API key never appears in
  request URLs or stored metadata; credential query params are redacted.
