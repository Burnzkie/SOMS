#!/bin/sh
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

# Roadmap Phase 2.2 -- previously nothing consumed the `jobs` table, so
# queued work (IssueSessionFinesJob) never ran. This starts a queue worker
# as a background process inside the same container.
#
# This is a pragmatic single-container compromise, not the ideal setup --
# the textbook-correct version is a *separate* Render worker service running
# `php artisan queue:work` on its own, so a crashed/restarted web process
# doesn't also kill the worker (and vice versa). If SOMS ever needs that
# reliability guarantee, split it out; render.yaml (added alongside this
# file) sketches that two-service layout as an alternative.
#
# --tries=3 + --backoff so a transient DB hiccup doesn't permanently fail a
# job; --max-time bounds each worker's lifetime so it recycles periodically
# rather than running forever with an ever-growing memory footprint.
php artisan queue:work \
    --tries=3 \
    --backoff=5 \
    --max-time=3600 \
    --sleep=3 &
QUEUE_PID=$!

# Forward termination signals to both children so `docker stop` / Render's
# redeploy doesn't leave an orphaned queue worker running past the web
# process's shutdown.
trap 'kill -TERM "$QUEUE_PID" 2>/dev/null; kill -TERM "$FRANKENPHP_PID" 2>/dev/null' TERM INT

frankenphp run --config /etc/caddy/Caddyfile &
FRANKENPHP_PID=$!

wait "$FRANKENPHP_PID"
