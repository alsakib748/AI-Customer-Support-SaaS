# Staging acceptance and go-live sign-off

Phase 6 (WS1–WS7). This is the end-to-end checklist a release must pass on
staging before production, and the record of who signed off.

> **Status: NOT executed.** No staging host, mail provider, PostgreSQL instance
> or secret store was available from this repository. Code and configuration
> artifacts are in-repo; every host-side step below must be completed and
> evidenced on real infrastructure.

---

## 0. Candidate

- Tag / commit SHA: `________________`
- Release artifact directory: `________________`
- Date: `________________`

---

## 1. Configuration (WS1)

- [ ] `php artisan app:production-check` exits 0 on the staging environment.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, app key and `JWT_SECRET` set.
- [ ] Payment mode and plan price mappings correct for the intended provider.
- [ ] CORS origins restricted to real frontend hosts.
- [ ] Secrets sourced from the host secret manager (see
      `secrets-and-configuration.md`); none in git.

Evidence: validator output, environment inventory.

## 2. Security (WS3)

- [ ] Password reset: email sent, token never in the response, no user
      enumeration.
- [ ] Cross-tenant access attempts return 403/404 and leak no data.
- [ ] Route `permission:` middleware enforced for the mapped endpoints.
- [ ] Auth/password rate limiters active (brute-force throttled).
- [ ] Security headers present on API responses.
- [ ] Webhook signature + `payment_status` verification rejects invalid payloads.

Evidence: manual probe results / screenshots, relevant test output.

## 3. Monitoring and health (WS4)

- [ ] `health:check` runs clean; dashboard shows all checks with a status.
- [ ] Stopping a dependency flips the relevant check unhealthy; restarting
      recovers it; an incident opens and auto-resolves.
- [ ] Scheduler and queue heartbeats are fresh.
- [ ] AI probe disabled unless deliberately enabled.
- [ ] Alert channel receives the incident (see
      `monitoring-and-alerting.md`, section 3).

Evidence: snapshots, incident notifications.

## 4. Backups and DR (WS5)

- [ ] Nightly backup job runs with exit code 0 for central + all tenants.
- [ ] Restore drill into `*_restore` passes; row counts and app smoke test OK.
- [ ] Achieved RPO/RTO recorded and within target.
- [ ] Offsite copy + retention/encryption configured.
- [ ] `backup.freshness` health check enabled and green.

Evidence: `backup-restore-runbook.md` drill checklist.

## 5. Dependencies, deployment, rollback (WS6)

- [ ] `composer audit` clean; `npm audit` exceptions documented
      (`dependency-audit.md`).
- [ ] Full backend suite green; frontend build green.
- [ ] Central and tenant migrations rehearsed against staging data.
- [ ] Rollback to the previous release rehearsed, including `queue:restart`.
- [ ] CI green (including the PostgreSQL-gated job if enabled).

Evidence: audit output, test/build logs, migration `--pretend` output, rollback
note.

## 6. Email delivery (WS7)

- [ ] Password-reset and invitation mails delivered to a real staging mailbox
      with SPF/DKIM pass.
- [ ] Queue workers processing mail jobs.
- [ ] Reset and dashboard links work end-to-end.

Evidence: `email-delivery-runbook.md` section 4.

## 6b. Frontend

- [ ] `npm run build` succeeds.
- [ ] Known lint debt acknowledged (see `dependency-audit.md`); no new lint
      errors introduced by this release.

---

## 6. Explicitly not executed (operational tasks)

These require infrastructure/credentials outside the repository and remain open
until performed and evidenced:

1. Staging deployment of the candidate release.
-   Real email delivery through a provider + mailbox.
- [ ] Backup restore drill into an isolated environment.
- [ ] Deployment rollback drill against a real prior release.
- [ ] Alert delivery verification to the configured channel.
- [ ] Production secret rotation / secret-store setup.

---

## 7. Sign-off

| Area | Owner | Result | Date |
| --- | --- | --- | --- |
| Configuration (WS1) | | | |
| Security (WS3) | | | |
| Monitoring (WS4) | | | |
| Backup/DR (WS5) | | | |
| Dependencies/deploy (WS6) | | | |
| Email (WS7) | | | |
| Overall go / no-go | | | |

A production release is **not approved** until every section above is checked
with evidence attached to the change ticket. Code completeness alone is not
production readiness.
