#!/bin/bash
# Azure App Service startup script for Laravel
# This runs every time the container starts

cd /home/site/wwwroot

# Fix permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Create required directories
mkdir -p storage/framework/{cache/data,sessions,views}
mkdir -p storage/logs

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM (default Azure behavior)
# The App Service will handle the web server
