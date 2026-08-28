#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan storage:link || true

# One-time-ish dev data (admin/officers/students/events). DevSeeder uses
# firstOrCreate throughout, so re-running on every boot is a no-op once
# seeded — safe to leave this on for a demo/capstone deploy. Remove before
# any real production use.
if [ "${SEED_DEV_DATA:-false}" = "true" ]; then
    php artisan db:seed --class=DevSeeder --force
fi

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"