# Phase 6 final production sign-off checklist

Status: **0 of 8 complete.** Every item below is a host-side operational check.
Code, configuration and runbooks are in-repo; the checks have **not** been
executed because no staging host, PostgreSQL instance, mail provider, secret
store or alert channel is available from this repository.

Do not treat Phase 6 as production-ready until every item is checked with
evidence attached to the change ticket.

| # | Check | Runbook / how | Evidence required | Owner | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Deploy to staging | `deployment-rollback-runbook.md` §1–5 | release dir/SHA, `app:production-check` exit 0, smoke test | | Pending |
| 2 | Verify scheduler and queues | `monitoring-and-alerting.md` §1–3 | `health:check`/heartbeat fresh, job processed, failed-job reporting, alert received | | Pending |
| 3 | Exercise security boundaries | `staging-acceptance-runbook.md` §2 | cross-tenant 403/404, role restrictions, invitation flow, webhook rejection, rate-limit hits | | Pending |
| 4 | Test real email delivery | `email-delivery-runbook.md` §4 | invitation email received, SPF/DKIM pass, no token in logs | | Pending |
| 5 | Run a restore drill | `backup-restore-runbook.md` §4 | central + tenant restored to `*_restore`, row counts, app smoke, RPO/RTO recorded | | Pending |
| 6 | Rehearse migration and rollback | `deployment-rollback-runbook.md` §3, §7 | central + tenant migration results, rollback switch timed | | Pending |
| 7 | Verify monitoring and alerting | `monitoring-and-alerting.md` §3 | dependency stop → unhealthy → incident → alert → recovery | | Pending |
| 8 | Approve dependency/lint exceptions | `dependency-audit.md`, `lint-baseline.md` | owner + security sign-off on npm advisory exceptions and lint baseline | | Pending |

## Item detail

1. **Deploy to staging** — production-like config, secrets injected from the
   secret manager (never committed), HTTPS, real queue workers. This is the
   prerequisite for items 2–7.
2. **Scheduler and queues** — confirm `health:check`, `health:heartbeat`,
   `billing:*` and `notifications:prune` run; a real job is processed; a failed
   job appears in `failed_jobs` and is reported; an incident alert is delivered.
3. **Security boundaries** — the automated regression tests cover much of this
   (`TenantAccessControlTest`, `RoutePermissionEnforcementTest`,
   `PasswordResetSecurityTest`, `CheckoutVerifyPaymentStatusTest`, the team
   invitation tests). Staging must confirm the live middleware/rate-limiter
   behaviour.
4. **Email delivery** — the only end-to-end proof that mail actually leaves the
   system; `Mail::fake()` tests cannot prove provider delivery.
5. **Restore drill** — the only proof backups are usable; see the drill
   checklist in `backup-restore-runbook.md`.
6. **Migration and rollback rehearsal** — proves the release can be reverted and
   that tenant migrations succeed on production-shaped data.
7. **Monitoring and alerting** — proves a failure is detected and a human is
   notified, not just that the dashboard renders.
8. **Exception approval** — the 9 npm advisories (build/deploy-toolchain only;
   see `dependency-audit.md`) and the 25-error lint baseline
   (`lint-baseline.md`) must be countersigned by the security lead with owner and
   deadline recorded.

## Not launch-blocking but tracked

- No frontend test runner (build is the only automated gate).
- CI PostgreSQL-gated job is opt-in (`ENABLE_POSTGRES_CI=true`); enabling it is a
  recommended pre-release gate.
- `NODE_ENV`/build toolchain exceptions have a 30-day remediation deadline
  (2026-11-13).

## Sign-off

| Role | Name | Date | Decision |
| --- | --- | --- | --- |
| Engineering | | | |
| Security | | | |
| Operations | | | |
| Overall go / no-go | | | |

Code completeness is not production readiness. The go/no-go decision is invalid
until items 1–8 carry evidence.
