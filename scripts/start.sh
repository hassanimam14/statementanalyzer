#!/usr/bin/env bash
set -euo pipefail

if [ ! -d /app/vendor ]; then
  composer install --no-dev --prefer-dist --no-interaction --no-ansi --no-progress
fi

php artisan storage:link || true

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force || true

php-fpm -D
nginx -g 'daemon off;'
