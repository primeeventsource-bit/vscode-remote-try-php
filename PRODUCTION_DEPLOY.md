# Production Deploy Guide — Prime CRM on Azure

## Critical .env Settings for Production

These MUST be set on Azure App Service > Configuration > Application Settings:

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning

QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true

FILESYSTEM_DISK=public
```

## After Deploy — Run Migrations

SSH into App Service or use the deploy script:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## Queue Worker Setup

The queue worker MUST run continuously. On Azure App Service, use a WebJob:

1. Create file `azure/queue-worker.sh` (already included)
2. Upload as a **continuous** WebJob in Azure Portal
3. Or use the App Service startup command approach

Alternative — add to `azure/startup.sh`:
```bash
# Start queue worker in background
nohup php /home/site/wwwroot/artisan queue:work database --sleep=3 --tries=3 --timeout=120 &
```

## Scheduler Setup

The scheduler MUST run every minute. Options:

### Option A: WebJob (recommended)
Upload `azure/scheduler-worker.sh` as a continuous Azure WebJob.

### Option B: Startup command
Add to `azure/startup.sh`:
```bash
nohup bash -c 'while true; do php /home/site/wwwroot/artisan schedule:run; sleep 60; done' &
```

### Option C: Azure Function Timer Trigger
Create an Azure Function that calls your app's scheduler endpoint every minute.

## Verify Production Health

After deploy, check:
1. Visit `/system-monitor` as master admin
2. Verify scheduler heartbeat is green (should update every minute)
3. Verify queue is processing (failed jobs count = 0)
4. Verify storage write test passes

## Security Checklist

- [ ] APP_DEBUG=false
- [ ] LOG_LEVEL=warning (not debug)
- [ ] Database credentials in Azure Key Vault, not .env
- [ ] OpenAI API key in Azure Key Vault
- [ ] Twilio credentials in Azure Key Vault
- [ ] HTTPS enforced
- [ ] SESSION_SECURE_COOKIE=true
