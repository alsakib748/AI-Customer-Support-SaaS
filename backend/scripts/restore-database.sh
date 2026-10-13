#!/usr/bin/env bash
#
# Phase 6 / WS5 - restore a single PostgreSQL dump produced by
# backup-databases.sh into a target database.
#
# This script is a repository artifact referenced by
# docs/production/backup-restore-runbook.md. It is NOT executed as part of
# Phase 6. Restoring into a NON-empty database is destructive: it drops and
# recreates the objects contained in the dump.
#
# Usage:
#   restore-database.sh <dump-file> <target-database>
#
# Required environment:
#   DB_HOST DB_PORT DB_USERNAME DB_PASSWORD
#
# Optional environment:
#   PG_RESTORE_BIN  (default pg_restore)
#   FORCE_RESTORE   must be "yes" to restore into a database whose name does
#                   not end in "_restore" or "_drill"
#   JOBS            pg_restore parallel jobs (default 1)
#
set -euo pipefail

dump_file="${1:?usage: restore-database.sh <dump-file> <target-database>}"
target_db="${2:?usage: restore-database.sh <dump-file> <target-database>}"

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

PG_RESTORE_BIN="${PG_RESTORE_BIN:-pg_restore}"
JOBS="${JOBS:-1}"
FORCE_RESTORE="${FORCE_RESTORE:-no}"

if [ ! -f "$dump_file" ]; then
  echo "[restore] dump file not found: ${dump_file}" >&2
  exit 1
fi

case "$target_db" in
  *_restore|*_drill) ;;
  *)
    if [ "$FORCE_RESTORE" != "yes" ]; then
      echo "[restore] refusing to restore into '${target_db}'." >&2
      echo "[restore] target must end in _restore or _drill, or set FORCE_RESTORE=yes." >&2
      exit 1
    fi
    ;;
esac

export PGPASSWORD="$DB_PASSWORD"

echo "[restore] restoring ${dump_file} -> ${target_db}"
"$PG_RESTORE_BIN" \
  --host "$DB_HOST" --port "$DB_PORT" --username "$DB_USERNAME" \
  --dbname "$target_db" \
  --clean --if-exists --no-owner --no-privileges \
  --exit-on-error --jobs "$JOBS" \
  "$dump_file"

echo "[restore] complete"
