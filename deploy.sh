#!/usr/bin/env bash
# Post-deploy hook for Laravel Cloud.
#
# Wire this up in the Cloud dashboard:
#   Environment -> Settings -> Deploy hook
#   Command: bash deploy.sh
#
# Runs migrations and rebuilds Laravel's framework caches so config/route/view
# changes from the new deploy take effect without a manual SSH step.

set -euo pipefail

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
