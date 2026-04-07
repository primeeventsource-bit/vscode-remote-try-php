#!/bin/bash
# ═══════════════════════════════════════════════════════
# Azure App Service startup script for Laravel CRM
# Runs every time the container starts.
# Starts: queue worker + scheduler as background processes.
# ═══════════════════════════════════════════════════════

echo "[Startup] $(date) — Initializing Laravel CRM..."

# ── Copy .env to ALL possible app roots ──────────────
# Azure Oryx copies code to /var/www/html but may skip hidden files.
# The CI-generated .env is in /home/site/wwwroot/.env
# We also save it as env.production (non-hidden) as a backup.
if [ -f /home/site/wwwroot/.env ]; then
    cp -f /home/site/wwwroot/.env /var/www/html/.env 2>/dev/null || true
    echo "[Startup] Copied .env to /var/www/html/"
fi
if [ -f /home/site/wwwroot/env.production ]; then
    cp -f /home/site/wwwroot/env.production /var/www/html/.env 2>/dev/null || true
    echo "[Startup] Copied env.production to /var/www/html/.env"
fi

cd /home/site/wwwroot

# ── Generate .env from Azure App Settings ────────────
echo "[Startup] Writing .env from Azure App Settings..."
cat > /home/site/wwwroot/.env << 'DOTENVEOF'
APP_NAME="Prime CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crmprime.online
DOTENVEOF

# Append all TWILIO_, DB_, OPENAI_, and other app vars from Azure env
for var in APP_KEY DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
           SESSION_DRIVER SESSION_LIFETIME SESSION_ENCRYPT SESSION_SECURE_COOKIE \
           CACHE_STORE QUEUE_CONNECTION FILESYSTEM_DISK LOG_CHANNEL LOG_LEVEL \
           TWILIO_ACCOUNT_SID TWILIO_AUTH_TOKEN TWILIO_API_KEY_SID TWILIO_API_KEY_SECRET \
           TWILIO_SMS_ENABLED TWILIO_WEBHOOK_VALIDATE_SIGNATURE TWILIO_DEFAULT_COUNTRY \
           TWILIO_LOG_RAW_WEBHOOKS TWILIO_MESSAGING_SERVICE_SID TWILIO_FROM_NUMBER \
           OPENAI_API_KEY OPENAI_MODEL \
           GIF_PROVIDER GIPHY_API_KEY TENOR_API_KEY TENOR_CLIENT_KEY \
           BROADCAST_CONNECTION REDIS_HOST REDIS_PASSWORD REDIS_PORT; do
    val="${!var}"
    if [ -n "$val" ]; then
        echo "${var}=${val}" >> /home/site/wwwroot/.env
    fi
done

echo "[Startup] .env written with $(wc -l < /home/site/wwwroot/.env) lines"

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
