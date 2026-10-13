# Backup and restore runbook

Phase 6, Workstream 5 (WS5). Companion to
`docs/production/monitoring-and-alerting.md`.

> **Status: scripts and runbook authored in-repo; NOT executed.** No staging or
> production host, PostgreSQL instance or object-storage credentials were
> available from this repository. Every step below that touches real
> infrastructure is an operational task with required evidence.

---

## 1. Targets

| Property | Target | Basis |
| --- | --- | --- |
| RPO (max data loss) | 24 hours | daily full logical dump |
| RTO (time to restore) | 2 hours (central + per-tenant) | measured in the drill below |
| Retention | 14 days local, 35 days offsite | `RETENTION_DAYS` + bucket lifecycle |
| Drill frequency | quarterly, and before any schema migration | change control |

These are provisional. Confirm and record the accepted business values in the
change ticket before go-live.

---

## 2. Backup model

The platform has **one central database** (`DB_DATABASE`) and **one database per
tenant** named `config('tenancy.database.prefix') . $tenantId`
(default prefix `tenant`, so `tenant{id}`). Backups are logical (`pg_dump
--format custom`), compressed, and written by
`backend/scripts/backup-databases.sh`.

`backend/scripts/backup-databases.sh`:

1. dumps the central database;
2. reads tenant ids from the central `tenants` table (or `TENANT_IDS` override)
   and dumps each tenant database;
2. writes a freshness marker (`BACKUP_MARKER_PATH`);
3. prunes local dumps older than `RETENTION_DAYS`.

Required env: `DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD`.
See the script header for the full optional set (`BACKUP_DIR`,
`TENANT_DB_TEMPLATE`, `PG_DUMP_BIN`, `RETENTION_DAYS`, `BACKUP_MARKER_PATH`).

### Schedule (cron / scheduler)

```cron
# 02:15 UTC daily
0 2 * * * cd /var/www/ai-support/backend && DB_* are inherited from the service env; \
  bash scripts/backup-databases.sh >> storage/logs/backup.log 2>&1
```

Credentials must come from the process environment / secret manager — never from
a checked-in file.

---

## 3. Freshness signal

Enable the `backup.freshness` health check by setting, in the environment:

```
HEALTH_BACKUP_CHECK_ENABLED=true
BACKUP_MARKER_PATH=/var/backups/ai-customer-support/.last_success
BACKUP_MAX_AGE_HOURS=26
```

The check (`app/Health/Checks/BackupFreshnessCheck.php`) reports `Disabled` when
unconfigured, `Unhealthy` when the marker is missing or older than
`BACKUP_MAX_AGE_HOURS`, and `Degraded` above 80% of the limit. The same marker is
written by the backup script when `BACKUP_MARKER_FOR_HEALTH=true` (default).

---

## 4. Restore procedure

Use `backend/scripts/restore-database.sh <dump-file> <target-database>`. It
refuses to run unless the target ends in `_restore` or `_drill` (or
`FORCE_RESTORE=yes` is set), to prevent an accidental overwrite of production.

```bash
# 1. Restore the central database into a scratch database.
createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" central_restore
restore-database.sh /var/backups/ai-customer-support/central_20260101T030000Z.dump central_restore

# 2. Restore one tenant database.
createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" tenant42_restore
restore-database.sh /var/backups/ai-customer-support/tenant42_20260101T030000Z.dump tenant42_restore

# 3. Sanity checks.
psql ... central_restore -c "select count(*) from tenants;"
psql ... tenant42_restore -c "select count(*) from users;"
```

### Drill checklist (evidence required)

- [ ] Backup run completed with exit code 0; per-database dump files present.
- [ ] Central dump restored into `*_restore`; row counts match source within
      the accepted window.
- [ ] At least one tenant dump restored into `*_restore`; tenant login smoke
      test passes against the restored central + tenant pair.
- [ ] Restore wall-clock time recorded against the RTO target.
- [ ] Application started against the restored databases (staging) and health
      dashboard shows core checks healthy.
- [ ] Restore artefacts (drill database, dump copy) deleted afterwards.
- [ ] Evidence attached to the change ticket: commands, timestamps, counts,
      elapsed time.

Record actual RPO/RTO achieved and update section 1.

---

## 5. Operational requirements (not implemented in-repo)

- Offsite copy: sync `${BACKUP_DIR}/*.dump` to object storage (e.g. `aws s3 cp`
  or `rclone`) with a 35-day lifecycle rule, encryption at rest, and versioning.
- Secrets: database credentials and bucket keys come from the host secret
  manager, never `.env` committed to the repo.
- Access: backup directory readable only by the deploy/ops account.
- Alerting: enable the section 3 check and route `backup.freshness` incidents
  through the channel described in `monitoring-and-alerting.md`.

---

## 6. Regression coverage (in-repo)

- `tests/Unit/Ops/BackupFreshnessCheckTest.php` — disabled / missing / healthy /
  degraded / unhealthy transitions.
- `tests/Unit/Health/HealthCheckRegistryConfigurationTest.php` — every
  configured check class exists, implements the contract and has a config entry
  (guards against a registry resolution failure disabling all monitoring).
