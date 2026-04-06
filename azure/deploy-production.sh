#!/bin/bash
# ═══════════════════════════════════════════════════════
# Laravel CRM — Production Deploy Script for Azure
# ═══════════════════════════════════════════════════════
# Run this on the Azure App Service after code push.
# It safely clears caches, runs migrations, and rebuilds.

set -e

echo "╔══════════════════════════════════════╗"
echo "║  Laravel CRM — Production Deploy     ║"
echo "╚══════════════════════════════════════╝"

cd /home/site/wwwroot || exit 1

# Step 1: Maintenance mode
echo "→ Entering maintenance mode..."
php artisan down --retry=30 || true

# Step 2: Install dependencies
echo "→ Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction 2>&1

# Step 3: Clear old caches
echo "→ Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Step 4: Run migrations
echo "→ Running database migrations..."
php artisan migrate --force --no-interaction 2>&1

# Step 5: Rebuild caches
echo "→ Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 6: Storage link
echo "→ Ensuring storage link..."
php artisan storage:link 2>/dev/null || true

# Step 7: Set permissions
echo "→ Setting permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Step 8: Exit maintenance
echo "→ Exiting maintenance mode..."
php artisan up

echo "╔══════════════════════════════════════╗"
echo "║  Deploy complete!                    ║"
echo "╚══════════════════════════════════════╝"
