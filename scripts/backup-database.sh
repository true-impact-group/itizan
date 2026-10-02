#!/usr/bin/env bash
# Dumps the Itizan PostgreSQL database and archives private uploads (Linux).
# Usage: BACKUP_DIR=/var/backups/itizan KEEP_DAYS=14 ./scripts/backup-database.sh   (run from the project root, e.g. via cron)
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/itizan}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

# shellcheck disable=SC2046
export $(grep -E '^(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' .env | sed 's/"//g' | xargs)

mkdir -p "$BACKUP_DIR"
PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -Fc -f "$BACKUP_DIR/itizan-$STAMP.dump" "$DB_DATABASE"
[ -d storage/app/private ] && tar -czf "$BACKUP_DIR/uploads-$STAMP.tar.gz" -C storage/app/private .
find "$BACKUP_DIR" -type f -mtime +"$KEEP_DAYS" -delete
echo "Backup written to $BACKUP_DIR ($STAMP)"
