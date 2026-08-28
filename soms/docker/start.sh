#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan storage:link || true

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"