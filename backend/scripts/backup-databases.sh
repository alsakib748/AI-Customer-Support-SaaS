#!/usr/bin/env bash
#
# Phase 6 / WS5 - PostgreSQL backup for the central database and every tenant
# database.
#
# This script is a repository artifact referenced by
# docs/production/backup-restore-runbook.md. It is NOT executed as part of
# Phase 6 (no host or PostgreSQL access was available).
#
# Required environment:
#   DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
#
# Optional environment:
#   BACKUP_DIR          output directory            (default /var/backups/ai-customer-support)
#   TENANT_DB_TEMPLATE  printf template for a tenant database name
#                       (default "tenant%s"; stancl/tenancy default is tenant{id})
#   TENANT_IDS          space-separated override; when unset the ids are read
#                       from the central `tenants` table
#   PG_DUMP_BIN         pg_dump binary              (default pg_dump)
#   RETENTION_DAYS      delete dumps older than N days (default 14)
#   BACKUP_MARKER_PATH  freshness marker file       (default ${BACKUP_DIR}/.last_success)
#   BACKUP_MARKER_FOR_HEALTH  "true" writes BACKUP_MARKER_PATH for the
#                       backup.freshness health check
#
set -euo pipefail

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE (central database) is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

BACKUP_DIR="${BACKUP_DIR:-/var/backups/ai-customer-support}"
TENANT_DB_TEMPLATE="${TENANT_DB_TEMPLATE:-tenant%s}"
PG_DUMP_BIN="${PG_DUMP_BIN:-pg_dump}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
BACKUP_MARKER_PATH="${BACKUP_MARKER_PATH:-${BACKUP_DIR}/.last_success}"
BACKUP_MARKER_FOR_HEALTH="${BACKUP_MARKER_FOR_HEALTH:-true}"

export PGPASSWORD="$DB_PASSWORD"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

echo "[backup] central database -> ${DB_DATABASE}_${timestamp}.dump"
"$PG_DUMP_BIN" \
  --host "$DB_HOST" --port "$DB_PORT" --username "$DB_USERNAME" \
  --format custom --compress 6 --no-owner --no-privileges \
  --file "${BACKUP_DIR}/${DB_DATABASE}_${timestamp}.dump" \
  "$DB_DATABASE"

if [ -z "${TENANT_IDS:-}" ]; then
  echo "[backup] discovering tenant ids from central database"
  TENANT_IDS="$(psql \
    --host "$DB_HOST" --port "$DB_PORT" --username "$DB_USERNAME" \
    --dbname "$DB_DATABASE" --tuples-only --no-align \
    --command "select id from tenants where deleted_at is null order by id")"
fi

for tenant_id in $TENANT_IDS; do
  # shellcheck disable=SC2059
  tenant_db="$(printf "$TENANT_DB_TEMPLATE" "$tenant_id")"
  out="${BACKUP_DIR}/${tenant_db}_${timestamp}.dump"
  echo "[backup] tenant ${tenant_id} -> ${out}"
  "$PG_DUMP_BIN" \
    --host "$DB_HOST" --port "$DB_PORT" --username "$DB_USERNAME" \
    --format custom --compress 6 --no-owner --no-privileges \
    --file "$out" "$tenant_db"
done

if [ "$BACKUP_MARKER_FOR_HEALTH" = "true" ]; then
  date -u +%Y-%m-%dT%H:%M:%SZ > "$BACKUP_MARKER_PATH"
  echo "[backup] wrote freshness marker ${BACKUP_MARKER_PATH}"
fi

if [ "${RETENTION_DAYS}" -gt 0 ]; then
  echo "[backup] pruning dumps older than ${RETENTION_DAYS} days"
  find "$BACKUP_DIR" -maxdepth 1 -name '*.dump' -type f -mtime "+${RETENTION_DAYS}" -delete
fi

echo "[backup] complete at ${timestamp}"
