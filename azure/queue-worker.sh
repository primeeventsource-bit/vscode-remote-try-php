#!/bin/bash
# ═══════════════════════════════════════════════════════
# Laravel CRM — Queue Worker for Azure WebJob
# ═══════════════════════════════════════════════════════
# Run this as a continuous Azure WebJob to process queued jobs.
# Azure WebJobs auto-restart on crash.

cd /home/site/wwwroot || exit 1

echo "[Queue Worker] Starting at $(date)"

while true; do
    php artisan queue:work database \
        --sleep=3 \
        --tries=3 \
        --timeout=120 \
        --max-time=3600 \
        --memory=256 \
        --no-interaction 2>&1

    # If queue:work exits (--max-time reached), restart after brief pause
    echo "[Queue Worker] Restarting at $(date)"
    sleep 2
done
