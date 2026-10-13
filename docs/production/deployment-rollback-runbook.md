# Deployment and rollback runbook

Phase 6, Workstream 6 (WS6).

> **Status: authored in-repo; NOT executed.** No staging or production host was
> available from this repository. The commands below are the contract the
> release process must follow; each run must produce the evidence listed in
> section 8.

---

## 1. Release model

Use **immutable releases**: every deploy builds a new versioned directory from a
git tag and the web/queue processes are repointed to it. Never deploy by editing
files in place. This makes rollback a matter of switching back to the previous
directory.

```
releases/20261013T120000Z/      <- new release (git archive / CI artifact)
current -> releases/20261013T120000Z
```

Artifacts:

- backend code (no `vendor/`, no `.env`)
- `vendor/` installed on the host with `composer install --no-dev --optimize-autoloader`
- frontend `dist/` from `npm ci && npm run build`
- environment and secrets from the host secret manager (never in the artifact)

---

## 2. Pre-deploy validation

Run on the release candidate (CI or a staging build):

```bash
# Backend
composer install --no-dev --optimize-autoloader --no-interaction
composer audit                       # must report no advisories (see dependency-audit.md)
php artisan app:production-check     # must exit 0
php vendor/bin/phpunit               # full suite green

# Frontend
npm ci
npm run build
```

`app:production-check` validates `APP_ENV`, `APP_DEBUG`, application/key/JWT
secrets, payment mode, mail transport, CORS and plan price mappings. A failure is
a release blocker.

---

## 3. Migration rehearsal (required before every schema change)

Rehearse **both** migration scopes against a clone of production-shaped data.

```bash
# 1. Central database
php artisan migrate --pretend        # inspect SQL first
php artisan migrate --force
php artisan migrate:rollback --step=1 --pretend   # confirm a reverse path exists

# 2. Tenant databases (stancl/tenancy)
php artisan tenants:migrate --tenants=<id>        # one tenant first
php artisan tenants:migrate                       # then all
```

Rules:

- Every new migration must have a working `down()` unless it is explicitly
  irreversible; irreversible migrations must be called out in the change ticket
  and are not rollback-safe.
- Rehearse on a copy of real data, not an empty schema, so data-dependent
  migrations and backfills are exercised.
- Measure the migration duration on the largest tenant; long locks need a
  maintenance window or an online strategy.
- Record central and tenant results separately (they use different connections).

---

## 4. Expand-and-contract (safe schema evolution)

Never perform a destructive or breaking schema change in the same release as the
code that stops using it. Use three phases across releases:

1. **Expand** — add the new structure without breaking the old.
   - add nullable column / new table / new index concurrently
   - deploy code that writes to both old and new and reads from old
2. **Migrate** — backfill in batches, then cut reads over to the new structure.
   - backfill job (chunked, idempotent, resumable)
   - deploy code that reads new, still writes both
3. **Contract** — remove the old structure only after no code references it.
   - drop the old column/table in a later release
   - keep a rollback window (at least one previous release) before contracting

Examples:

- Rename column: add new column → dual-write → backfill → switch reads → stop
  writing old → drop old (never a single `renameColumn` in a live release).
- Change type / add `NOT NULL`: add nullable → backfill → add constraint in a
  follow-up release.

---

## 5. Deploy procedure

```bash
# On the host, for the new release directory
composer install --no-dev --optimize-autoloader --no-interaction

php artisan down --render="errors::503" --retry=60    # only if a migration needs it
php artisan migrate --force                           # central
php artisan tenants:migrate                           # all tenants
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart        # old workers finish current jobs, then reload
php artisan up

# Repoint the symlink / reload the process manager
```

Then run the post-deploy check in section 5.

---

## 6. Post-deploy verification

```bash
php artisan app:production-check
php artisan health:check --verbose
php artisan queue:monitor default:100
```

- Health dashboard shows core checks healthy (no `unknown` from stale or
  unresolved results).
- A test login, one tenant login, one billing webhook replay in staging, and a
  test email (see `email-delivery-runbook.md`) succeed.
- Queue workers are running the new release (`queue:restart` issued).

---

## 7. Rollback

Rollback is a **release switch**, not a code edit:

```bash
# 1. Repoint the symlink to the previous release directory.
ln -sfn releases/<previous> current

# 2. Restart long-lived processes so they pick up the old code.
php artisan queue:restart
php artisan optimize:clear

# 3. Only if the release applied a migration with no safe reverse path:
#    restore the affected database(s) from the pre-deploy backup and revert the
#    schema using the recorded down()/manual script.
```

Because schema changes follow expand-and-contract (section 4), the previous
release must still run against the migrated schema. If it cannot, the migration
was not expand-and-contract safe — treat that as a process failure and restore
from backup.

---

## 8. Required evidence per release

- [ ] Tag / commit SHA and release directory name.
- [ ] `composer audit` output (clean) and `npm audit` summary.
- [ ] `app:production-check` output (exit 0).
- [ ] Full backend test suite result and frontend build result.
- [ ] Central migration `--pretend` output and applied result.
- [ ] Tenant migration results (per tenant, or a batched summary).
- [ ] Post-deploy health snapshot and smoke-test notes.
- [ ] Rollback rehearsal note (previous release target and time to switch).

Store the evidence in the change ticket; this repository cannot produce host-side
evidence on its own.
