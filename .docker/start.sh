#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# Background workers (Phase 0 infra for reminders / reconciliation / webhooks).
#
# This image bundles everything in one container, so the queue worker and the
# scheduler run here as supervised background loops (auto-restart on crash).
# In the k8s setup (Dockerfile.dev), run these instead as separate Deployments
# off the same image:
#     command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3"]
#     command: ["php", "artisan", "schedule:work"]
# ---------------------------------------------------------------------------

# Drains the database queue (queued mail, reminder/webhook jobs, async work).
( while true; do
    php artisan queue:work --sleep=3 --tries=3 --max-time=3600 || true
    sleep 2
  done ) &

# Runs the scheduler in-process every minute — no external cron needed.
# Activates the (previously dormant) Schedule::command(...) in routes/console.php.
( while true; do
    php artisan schedule:work || true
    sleep 2
  done ) &

php-fpm -D
exec nginx -g 'daemon off;'
