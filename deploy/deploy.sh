#!/usr/bin/env bash
# One-command release on the server:  bash deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."
php artisan down --retry=30 || true
php artisan abmm:backup            # safety copy BEFORE migrating
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
php artisan abmm:go-live-check || true
