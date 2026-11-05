#!/bin/sh
# Nightly database backup. Run from the repo root, e.g. via host cron:
#   15 3 * * * cd /srv/filament && ./docker/backup.sh >> /var/log/filament-backup.log 2>&1
# Copy BACKUP_DIR off-host afterwards (rclone, restic, rsync); a backup on the same disk is not a backup.
# Restore test:
#   gunzip -c backups/filament-YYYYmmdd-HHMMSS.sql.gz | docker compose exec -T db psql -U "$DB_USERNAME" -d "$DB_DATABASE"
set -eu

BACKUP_DIR="${BACKUP_DIR:-./backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
mkdir -p "$BACKUP_DIR"

file="$BACKUP_DIR/filament-$(date +%Y%m%d-%H%M%S).sql.gz"
docker compose exec -T db sh -c 'pg_dump --clean --if-exists -U "$POSTGRES_USER" -d "$POSTGRES_DB"' | gzip > "$file.tmp"
mv "$file.tmp" "$file"

find "$BACKUP_DIR" -name 'filament-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
echo "Backup written: $file"
