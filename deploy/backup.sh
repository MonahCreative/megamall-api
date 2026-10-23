#!/usr/bin/env bash
# =============================================================================
# Daily backup: database + uploaded photos. Keeps 14 days on the server.
# Scheduled by crontab.txt (02:30 every night). Also run by deploy.sh before each update.
#
# Copies on the same server are lost if the server is lost: set up the off-site copy at the bottom.
# =============================================================================
set -euo pipefail
API=/var/www/megamall-api
DEST=/var/backups/megamall
KEEP_DAYS=14
STAMP=$(date +%Y-%m-%d_%H%M)

# Read the database login from the app's .env
env_value() { grep -E "^$1=" "$API/.env" | tail -1 | cut -d= -f2- | sed -e 's/[[:space:]]#.*$//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//'; }
DB_NAME=$(env_value DB_DATABASE)
DB_USER=$(env_value DB_USERNAME)
DB_PASS=$(env_value DB_PASSWORD)

mkdir -p "$DEST"
MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --routines --no-tablespaces -u "$DB_USER" "$DB_NAME" | gzip > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/photos-$STAMP.tar.gz" -C "$API/storage/app" public

find "$DEST" -type f -mtime +"$KEEP_DAYS" -delete
echo "Backup saved: $DEST/db-$STAMP.sql.gz and photos-$STAMP.tar.gz"

# --- Off-site copy (recommended) -------------------------------------------
# 1. On the server: sudo apt install rclone && rclone config   (e.g. Google Drive or Backblaze B2, name it "offsite")
# 2. Remove the # from the next line:
# rclone copy "$DEST" offsite:megamall-backups --max-age 2d
