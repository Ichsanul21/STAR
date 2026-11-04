#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/star/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"

cd "$APP_DIR"

echo "==> Pulling latest code"
git pull --ff-only

echo "==> Installing PHP dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Building frontend"
npm ci
npm run build

echo "==> Running migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Optimizing"
"$PHP_BIN" artisan optimize

echo "==> Restarting workers"
"$PHP_BIN" artisan queue:restart
sudo systemctl restart star-reverb.service

echo "==> Done"
