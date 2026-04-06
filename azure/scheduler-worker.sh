#!/bin/bash
# ═══════════════════════════════════════════════════════
# Laravel CRM — Scheduler Worker for Azure WebJob
# ═══════════════════════════════════════════════════════
# Run this as a continuous Azure WebJob to execute scheduled tasks.
# Runs `php artisan schedule:run` every 60 seconds.

cd /home/site/wwwroot || exit 1

echo "[Scheduler Worker] Starting at $(date)"

while true; do
    php artisan schedule:run --no-interaction 2>&1
    sleep 60
done
