# Production Deploy Guide — Prime CRM on Laravel Cloud

Deployment is GitHub → Laravel Cloud. The `azure/` directory in this repo is
legacy and not used by the current deployment.

## 1. Environment Variables (Laravel Cloud → Environment → Variables)

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning

QUEUE_CONNECTION=redis        # use redis if you provisioned the Cloud Redis add-on
                              # otherwise: database (and ensure jobs table is migrated)
CACHE_STORE=redis             # or database
SESSION_DRIVER=redis          # or database
SESSION_SECURE_COOKIE=true

FILESYSTEM_DISK=public

# Database, mail, twilio, openai, etc. — set as Cloud env vars (encrypted at rest)
```

## 2. Worker Process (REQUIRED — CSV imports depend on this)

In the Laravel Cloud dashboard for the production environment:

1. Open **Workers** → **Add Worker**
2. Command: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=300`
3. Connection: matches `QUEUE_CONNECTION` (redis or database)
4. Replicas: 1 (scale up if import volume grows)

Without a worker, lead imports will dispatch jobs that never run and the
"Processing..." UI will hang. The `leads:recover-stuck-imports` scheduled
command auto-fails them after 30 min, but the import itself won't complete.

## 3. Scheduler

In the Laravel Cloud dashboard:

1. Open **Scheduler** → toggle **Enabled**

Cloud runs `php artisan schedule:run` every minute. All scheduled commands
in `routes/console.php` (heartbeat, presence, duplicate scan, stuck-import
recovery, weekly stats, etc.) are picked up automatically.

## 4. Post-Deploy Hooks

Add to the Laravel Cloud **Build / Deploy hooks** section (or your `.cloud/`
config if using file-based config):

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## 5. Verify Production Health

After deploy:
1. Visit `/system-monitor` as master admin
2. Scheduler heartbeat is green (updates every minute)
3. Queue is processing (failed jobs count = 0)
4. Storage write test passes
5. Test a small CSV import (10 rows) and confirm it reaches `completed`
   status on `/lead-imports` within ~30s

## 6. Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] `LOG_LEVEL=warning` (not debug)
- [ ] Secrets stored in Laravel Cloud env vars (encrypted at rest), not in repo `.env`
- [ ] HTTPS enforced (Cloud handles by default)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Worker process running (see step 2)
- [ ] Scheduler enabled (see step 3)
