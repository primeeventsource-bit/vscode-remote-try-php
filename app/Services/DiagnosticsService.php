<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * System diagnostics for the admin health panel.
 */
class DiagnosticsService
{
    public function fullReport(): array
    {
        return [
            'environment' => $this->environment(),
            'database' => $this->database(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
            'scheduler' => $this->scheduler(),
            'storage' => app(StorageService::class)->healthCheck(),
            'twilio' => $this->twilio(),
        ];
    }

    public function environment(): array
    {
        return [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'queue_connection' => config('queue.default'),
            'cache_store' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'filesystem_disk' => config('filesystems.default'),
        ];
    }

    public function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 2);
            return ['status' => 'healthy', 'response_ms' => $ms, 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'error' => $e->getMessage()];
        }
    }

    public function cache(): array
    {
        try {
            $key = '_diag_cache_test_' . time();
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);
            return ['status' => $val === 'ok' ? 'healthy' : 'degraded', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            return ['status' => 'degraded', 'error' => $e->getMessage()];
        }
    }

    public function queue(): array
    {
        $driver = config('queue.default');
        $result = ['driver' => $driver, 'status' => 'unknown'];

        if ($driver === 'sync') {
            $result['status'] = 'degraded';
            $result['warning'] = 'Using sync queue — jobs run synchronously. Switch to database or redis for production.';
            return $result;
        }

        try {
            $failedCount = DB::table('failed_jobs')->count();
            $pendingCount = 0;

            if ($driver === 'database') {
                $pendingCount = DB::table('jobs')->count();
            }

            $result['status'] = $failedCount > 10 ? 'degraded' : 'healthy';
            $result['failed_jobs'] = $failedCount;
            $result['pending_jobs'] = $pendingCount;
        } catch (\Throwable $e) {
            $result['status'] = 'unknown';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function scheduler(): array
    {
        try {
            $latest = DB::table('scheduler_heartbeats')
                ->orderByDesc('ran_at')
                ->first();

            if (!$latest) {
                return ['status' => 'unknown', 'message' => 'No heartbeat recorded yet'];
            }

            $lastRan = \Carbon\Carbon::parse($latest->ran_at);
            $stale = $lastRan->lt(now()->subMinutes(3));

            return [
                'status' => $stale ? 'degraded' : 'healthy',
                'last_ran' => $lastRan->toDateTimeString(),
                'seconds_ago' => $lastRan->diffInSeconds(now()),
                'command' => $latest->command ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    public function twilio(): array
    {
        $sid = config('services.twilio.account_sid', config('twilio.account_sid'));
        $apiKey = config('services.twilio.api_key', config('twilio.api_key_sid'));
        $apiSecret = config('services.twilio.api_secret', config('twilio.api_key_secret'));

        return [
            'account_sid_set' => !empty($sid),
            'api_key_set' => !empty($apiKey),
            'api_secret_set' => !empty($apiSecret),
            'status' => ($sid && $apiKey && $apiSecret) ? 'configured' : 'missing_credentials',
        ];
    }

    /**
     * Retry all failed jobs.
     */
    public function retryFailedJobs(): int
    {
        try {
            $count = DB::table('failed_jobs')->count();
            if ($count > 0) {
                \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);
            }
            return $count;
        } catch (\Throwable $e) {
            Log::error('retryFailedJobs failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Clear and rebuild all caches.
     */
    public function rebuildCaches(): array
    {
        $results = [];

        $commands = ['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'config:cache', 'route:cache', 'view:cache'];
        foreach ($commands as $cmd) {
            try {
                \Illuminate\Support\Facades\Artisan::call($cmd);
                $results[$cmd] = 'ok';
            } catch (\Throwable $e) {
                $results[$cmd] = 'failed: ' . $e->getMessage();
            }
        }

        return $results;
    }
}
