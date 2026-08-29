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

# One-time production admin creation. Set SEED_ADMIN=true, deploy, check the
# deploy logs for the printed student_id/password, then set SEED_ADMIN back
# to false (or unset it) and redeploy — AdminSeeder is idempotent (it exits
# quietly if the admin already exists) so leaving it "true" isn't dangerous,
# but the password is only shown in the logs from the run that created it.
if [ "${SEED_ADMIN:-false}" = "true" ]; then
    php artisan db:seed --class=AdminSeeder --force
fi

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"