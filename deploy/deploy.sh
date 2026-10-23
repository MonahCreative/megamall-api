#!/usr/bin/env bash
# =============================================================================
# Put the latest code live. Run on the server as the deploy user:
#     bash /var/www/megamall-api/deploy/deploy.sh
# Pulls both projects from git, installs, migrates the database, rebuilds the shop,
# restarts. Takes a backup first. The shop stays up except for a few seconds.
# =============================================================================
set -euo pipefail
API=/var/www/megamall-api
WEB=/var/www/megamall-web

echo "==> Backup before updating"
bash "$API/deploy/backup.sh"

echo "==> API and admin"
cd "$API"
php artisan down --retry=15 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan up
sudo /usr/bin/systemctl reload php8.4-fpm

echo "==> Shop"
cd "$WEB"
git pull --ff-only
npm ci
npm run build
sudo /usr/bin/systemctl restart megamall-web

echo "==> Done. Check https://megamall.co.tz and https://api.megamall.co.tz/admin"
