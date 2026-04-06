#!/bin/bash
# ═══════════════════════════════════════════════════════
# Azure App Service startup script for Laravel CRM
# Runs every time the container starts.
# Starts: queue worker + scheduler as background processes.
# ═══════════════════════════════════════════════════════

cd /home/site/wwwroot

echo "[Startup] $(date) — Initializing Laravel CRM..."

# Fix permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null

# Create required directories
mkdir -p storage/framework/{cache/data,sessions,views}
mkdir -p storage/logs
mkdir -p storage/app/public/uploads

# Storage symlink
php artisan storage:link 2>/dev/null || true

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations (non-blocking)
echo "[Startup] Running migrations..."
php artisan migrate --force 2>&1 || true

# Clear caches — do NOT use config:cache on Azure App Service
# Azure injects env vars at runtime; config:cache would bake in empty values
echo "[Startup] Clearing caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# ── Start Queue Worker in background ─────────────────
echo "[Startup] Starting queue worker..."
nohup php artisan queue:work database \
    --sleep=3 --tries=3 --timeout=120 --memory=128 --no-interaction \
    >> /home/LogFiles/queue-worker.log 2>&1 &
echo "[Startup] Queue worker PID: $!"

# ── Start Scheduler in background ────────────────────
echo "[Startup] Starting scheduler loop..."
nohup bash -c 'while true; do php /home/site/wwwroot/artisan schedule:run --no-interaction >> /home/LogFiles/scheduler.log 2>&1; sleep 60; done' &
echo "[Startup] Scheduler PID: $!"

echo "[Startup] $(date) — Ready. Queue + Scheduler running."
