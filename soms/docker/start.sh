#!/bin/sh
# start.sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan storage:link || true

# One-time-ish dev data (admin/officers/students/events). DevSeeder uses
# firstOrCreate throughout, so re-running on every boot is a no-op once
# seeded -- safe to leave this on for a demo/capstone deploy. Remove before
# any real production use.
if [ "${SEED_DEV_DATA:-false}" = "true" ]; then
    php artisan db:seed --class=DevSeeder --force
fi

# One-time production admin creation. Set SEED_ADMIN=true, deploy, check the
# deploy logs for the printed student_id/password, then set SEED_ADMIN back
# to false (or unset it) and redeploy -- AdminSeeder is idempotent (it exits
# quietly if the admin already exists) so leaving it "true" isn't dangerous,
# but the password is only shown in the logs from the run that created it.
if [ "${SEED_ADMIN:-false}" = "true" ]; then
    php artisan db:seed --class=AdminSeeder --force
fi

# Queue worker (consumes the `jobs` table, e.g. IssueSessionFinesJob).
#
# Runs inside a restart loop: --max-time=3600 makes the worker exit after an
# hour (to recycle memory), and without the loop nothing would bring it
# back -- jobs would pile up silently and fines would stop being issued.
# The loop also restarts the worker if it ever crashes.
#
# This is still the single-container compromise, not the ideal setup: the
# textbook version is a separate Render worker service (see render.yaml).
# Do NOT run both, or you will exceed Clever Cloud's 5-connection cap.
(
    while true; do
        php artisan queue:work \
            --tries=3 \
            --backoff=5 \
            --max-time=3600 \
            --sleep=3 || true
        sleep 2
    done
) &
QUEUE_PID=$!

# Scheduler: laravel only runs scheduled commands (routes/console.php) when
# `schedule:run` is called, and it must tick once a minute. This is what
# triggers IssueDueSessionFines every 15 minutes and the daily
# logs:verify check. Without this loop, scheduled fines never get issued.
(
    while true; do
        php artisan schedule:run > /dev/null 2>&1 || true
        sleep 60
    done
) &
SCHEDULER_PID=$!

# Forward termination signals to all children so `docker stop` / Render's
# redeploy doesn't leave orphaned background loops running past the web
# process's shutdown.
trap 'kill -TERM "$QUEUE_PID" "$SCHEDULER_PID" "$FRANKENPHP_PID" 2>/dev/null' TERM INT

frankenphp run --config /etc/caddy/Caddyfile &
FRANKENPHP_PID=$!

wait "$FRANKENPHP_PID"