<?php

namespace App\Services\Monitor;

use App\Models\SystemHealthCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Runs all health checks and stores results.
 * Called by the scheduler every 5 minutes.
 */
class HealthCheckRunner
{
    public static function runAll(): array
    {
        $results = [];

        $checks = [
            'database'  => [self::class, 'checkDatabase'],
            'queue'     => [self::class, 'checkQueue'],
            'storage'   => [self::class, 'checkStorage'],
            'chat'      => [self::class, 'checkChat'],
            'scheduler' => [self::class, 'checkScheduler'],
            'security'  => [self::class, 'checkSecurity'],
            'app'       => [self::class, 'checkApp'],
        ];

        foreach ($checks as $component => $callable) {
            $start = microtime(true);
            try {
                $result = call_user_func($callable);
                $ms = (int) ((microtime(true) - $start) * 1000);
                $result['response_time_ms'] = $ms;
            } catch (\Throwable $e) {
                $result = [
                    'status'  => 'critical',
                    'details' => ['error' => $e->getMessage()],
                    'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
                ];
            }

            $results[$component] = $result;

            try {
                SystemHealthCheck::create([
                    'component'       => $component,
                    'status'          => $result['status'],
                    'details'         => $result['details'] ?? null,
                    'response_time_ms' => $result['response_time_ms'] ?? null,
                    'checked_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                // Table might not exist yet
            }

            // Auto-open incident if critical
            if ($result['status'] === 'critical') {
                IncidentManager::openIfNew($component, 'critical', "Health check failed: {$component}", $result['details'] ?? []);
            }
        }

        return $results;
    }

    // ── Individual Checks ────────────────────────────────

    public static function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $version = DB::selectOne('SELECT 1 as ok');
            $ms = (int) ((microtime(true) - $start) * 1000);

            $userCount = DB::table('users')->count();
            $leadCount = DB::table('leads')->count();

            return [
                'status'  => $ms > 5000 ? 'degraded' : 'healthy',
                'details' => [
                    'latency_ms' => $ms,
                    'users'      => $userCount,
                    'leads'      => $leadCount,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'details' => ['error' => $e->getMessage()]];
        }
    }

    public static function checkQueue(): array
    {
        try {
            $failedCount = DB::table('failed_jobs')->count();
            $pendingCount = 0;

            try {
                $pendingCount = DB::table('jobs')->count();
            } catch (\Throwable $e) {
                // jobs table may not exist if using sync driver
            }

            $status = 'healthy';
            if ($failedCount > 10) $status = 'degraded';
            if ($failedCount > 50) $status = 'critical';

            return [
                'status'  => $status,
                'details' => [
                    'pending'  => $pendingCount,
                    'failed'   => $failedCount,
                    'driver'   => config('queue.default'),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'details' => ['error' => $e->getMessage()]];
        }
    }

    public static function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            $read = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);

            $publicLinked = is_dir(public_path('storage'));

            $status = ($read === 'ok') ? 'healthy' : 'critical';
            if (! $publicLinked) $status = 'degraded';

            return [
                'status'  => $status,
                'details' => [
                    'writable'      => $read === 'ok',
                    'public_linked' => $publicLinked,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'details' => ['error' => $e->getMessage()]];
        }
    }

    public static function checkChat(): array
    {
        try {
            $hasChatTable = Schema::hasTable('chats');
            $hasMessageTable = Schema::hasTable('messages');

            if (! $hasChatTable || ! $hasMessageTable) {
                return ['status' => 'critical', 'details' => ['error' => 'Chat tables missing']];
            }

            $recentMessages = DB::table('messages')
                ->where('created_at', '>', now()->subHours(24))
                ->count();

            return [
                'status'  => 'healthy',
                'details' => [
                    'messages_24h' => $recentMessages,
                    'broadcast'    => config('broadcasting.default'),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'degraded', 'details' => ['error' => $e->getMessage()]];
        }
    }

    public static function checkScheduler(): array
    {
        try {
            if (! Schema::hasTable('scheduler_heartbeats')) {
                return ['status' => 'unknown', 'details' => ['error' => 'Heartbeat table not created yet']];
            }

            $lastBeat = DB::table('scheduler_heartbeats')
                ->orderByDesc('ran_at')
                ->first();

            if (! $lastBeat) {
                return ['status' => 'degraded', 'details' => ['error' => 'No scheduler heartbeats recorded']];
            }

            $minutesAgo = now()->diffInMinutes($lastBeat->ran_at);
            $status = $minutesAgo <= 10 ? 'healthy' : ($minutesAgo <= 30 ? 'degraded' : 'critical');

            return [
                'status'  => $status,
                'details' => [
                    'last_run'    => $lastBeat->ran_at,
                    'minutes_ago' => $minutesAgo,
                    'last_command' => $lastBeat->command,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'details' => ['error' => $e->getMessage()]];
        }
    }

    public static function checkSecurity(): array
    {
        $issues = [];

        if (config('app.debug') === true && config('app.env') === 'production') {
            $issues[] = 'APP_DEBUG is true in production';
        }
        if (config('app.key') === '' || config('app.key') === null) {
            $issues[] = 'APP_KEY is not set';
        }
        if (config('session.secure') === false && config('app.env') === 'production') {
            $issues[] = 'Session cookies not secure in production';
        }

        // Check for exposed .env
        $envPublic = file_exists(public_path('.env'));
        if ($envPublic) $issues[] = '.env file accessible in public directory';

        $status = empty($issues) ? 'healthy' : (count($issues) > 2 ? 'critical' : 'degraded');

        return [
            'status'  => $status,
            'details' => ['issues' => $issues, 'count' => count($issues)],
        ];
    }

    public static function checkApp(): array
    {
        return [
            'status'  => 'healthy',
            'details' => [
                'env'     => config('app.env'),
                'debug'   => config('app.debug'),
                'php'     => PHP_VERSION,
                'laravel' => app()->version(),
                'uptime'  => round((microtime(true) - LARAVEL_START) * 1000) . 'ms',
            ],
        ];
    }
}
