<?php

namespace App\Services;

use App\Models\HealingAction;
use App\Models\QueueHealthSnapshot;
use App\Models\SchedulerRunLog;
use App\Models\StorageEvent;
use App\Services\Monitor\IncidentManager;
use App\Services\Storage\StorageHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Unified self-healing orchestrator.
 * Runs queue + scheduler + storage health checks as one coordinated system.
 * Detects degraded subsystems and applies safe automated recovery.
 */
class SelfHealingOrchestrator
{
    /**
     * Run the full healing cycle. Called by `system:heal` command every 5 minutes.
     */
    public static function run(): array
    {
        $results = [
            'queue'     => self::healQueue(),
            'scheduler' => self::healScheduler(),
            'storage'   => self::healStorage(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Record orchestrator heartbeat
        SchedulerRunLog::record('system:heal', 'success');

        return $results;
    }

    // ═══════════════════════════════════════════════════════
    // QUEUE HEALING
    // ═══════════════════════════════════════════════════════

    public static function healQueue(): array
    {
        $result = ['state' => 'healthy', 'actions' => []];

        try {
            $driver = config('queue.default', 'sync');
            $pendingJobs = 0;
            $failedJobs = 0;
            $oldestPendingSeconds = null;

            // Count pending jobs
            if ($driver !== 'sync') {
                try {
                    $pendingJobs = DB::table('jobs')->count();
                    $oldest = DB::table('jobs')->orderBy('created_at')->first();
                    if ($oldest) {
                        $oldestPendingSeconds = now()->diffInSeconds($oldest->created_at);
                    }
                } catch (\Throwable $e) {}
            }

            // Count failed jobs
            try {
                $failedJobs = DB::table('failed_jobs')->count();
            } catch (\Throwable $e) {}

            // Determine state
            $state = 'healthy';
            if ($failedJobs > 50) $state = 'critical';
            elseif ($failedJobs > 10) $state = 'lagging';
            elseif ($oldestPendingSeconds && $oldestPendingSeconds > 300) $state = 'stuck';
            elseif ($pendingJobs > 100) $state = 'lagging';

            $result['state'] = $state;
            $result['pending'] = $pendingJobs;
            $result['failed'] = $failedJobs;
            $result['oldest_seconds'] = $oldestPendingSeconds;
            $result['driver'] = $driver;

            // Record snapshot
            try {
                QueueHealthSnapshot::create([
                    'queue'                  => 'default',
                    'connection'             => $driver,
                    'pending_jobs'           => $pendingJobs,
                    'failed_jobs'            => $failedJobs,
                    'oldest_pending_seconds' => $oldestPendingSeconds,
                    'state'                  => $state,
                    'recorded_at'            => now(),
                ]);
            } catch (\Throwable $e) {}

            // ── Auto-heal: retry failed jobs (max 20 at a time) ──
            if ($failedJobs > 0 && $failedJobs <= 50) {
                $healed = self::executeHeal('queue', 'retry_failed_jobs', 'auto_health_check', function () use ($failedJobs) {
                    $count = min($failedJobs, 20);
                    try {
                        Artisan::call('queue:retry', ['id' => 'all']);
                        return ['success' => true, 'retried' => $count];
                    } catch (\Throwable $e) {
                        return ['success' => false, 'error' => $e->getMessage()];
                    }
                });
                $result['actions'][] = $healed;
            }

            // ── Auto-heal: flush ancient failed jobs (>7 days old) ──
            if ($failedJobs > 50) {
                $healed = self::executeHeal('queue', 'flush_old_failed', 'auto_health_check', function () {
                    try {
                        $deleted = DB::table('failed_jobs')
                            ->where('failed_at', '<', now()->subDays(7))
                            ->delete();
                        return ['success' => true, 'flushed' => $deleted];
                    } catch (\Throwable $e) {
                        return ['success' => false, 'error' => $e->getMessage()];
                    }
                });
                $result['actions'][] = $healed;

                IncidentManager::openIfNew('queue', 'critical', "Queue has {$failedJobs} failed jobs", [
                    'failed' => $failedJobs, 'pending' => $pendingJobs,
                ]);
            }

            // ── Detect stuck queue (jobs pending >10 min, no processing) ──
            if ($state === 'stuck') {
                IncidentManager::openIfNew('queue', 'warning', "Queue appears stuck — oldest job {$oldestPendingSeconds}s old", [
                    'oldest_seconds' => $oldestPendingSeconds,
                    'pending' => $pendingJobs,
                ]);
            }

        } catch (\Throwable $e) {
            $result['state'] = 'error';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════
    // SCHEDULER HEALING
    // ═══════════════════════════════════════════════════════

    public static function healScheduler(): array
    {
        $result = ['state' => 'healthy', 'actions' => []];

        try {
            // Check: has the scheduler run recently?
            $lastRun = null;
            try {
                if (Schema::hasTable('scheduler_run_log')) {
                    $lastRun = SchedulerRunLog::orderByDesc('ran_at')->first();
                } elseif (Schema::hasTable('scheduler_heartbeats')) {
                    $lastRun = DB::table('scheduler_heartbeats')->orderByDesc('ran_at')->first();
                }
            } catch (\Throwable $e) {}

            if (! $lastRun) {
                $result['state'] = 'unknown';
                $result['message'] = 'No scheduler heartbeats recorded';
                return $result;
            }

            $ranAt = $lastRun->ran_at ?? null;
            $minutesAgo = $ranAt ? now()->diffInMinutes($ranAt) : 999;

            $result['last_run'] = $ranAt;
            $result['minutes_ago'] = $minutesAgo;

            if ($minutesAgo > 30) {
                $result['state'] = 'critical';
                IncidentManager::openIfNew('scheduler', 'critical', "Scheduler has not run in {$minutesAgo} minutes", [
                    'last_run' => $ranAt, 'minutes_ago' => $minutesAgo,
                ]);
            } elseif ($minutesAgo > 10) {
                $result['state'] = 'degraded';
                IncidentManager::openIfNew('scheduler', 'warning', "Scheduler delayed — last run {$minutesAgo} minutes ago");
            }

            // Check for failed scheduled commands in last hour
            $failedCommands = 0;
            try {
                if (Schema::hasTable('scheduler_run_log')) {
                    $failedCommands = SchedulerRunLog::where('status', 'failed')
                        ->where('ran_at', '>', now()->subHour())
                        ->count();
                }
            } catch (\Throwable $e) {}

            $result['failed_commands_1h'] = $failedCommands;

            if ($failedCommands > 3) {
                IncidentManager::openIfNew('scheduler', 'warning', "{$failedCommands} scheduled commands failed in last hour");
            }

        } catch (\Throwable $e) {
            $result['state'] = 'error';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════
    // STORAGE HEALING
    // ═══════════════════════════════════════════════════════

    public static function healStorage(): array
    {
        try {
            $checkResult = StorageHealthService::runFullCheck();

            // Fix storage symlink if broken
            if (! is_link(public_path('storage'))) {
                self::executeHeal('storage', 'fix_symlink', 'auto_health_check', function () {
                    try {
                        Artisan::call('storage:link');
                        return ['success' => true, 'message' => 'Symlink created'];
                    } catch (\Throwable $e) {
                        return ['success' => false, 'error' => $e->getMessage()];
                    }
                });
            }

            return $checkResult;
        } catch (\Throwable $e) {
            return ['state' => 'error', 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════
    // HEALING ACTION EXECUTOR
    // ═══════════════════════════════════════════════════════

    /**
     * Execute a healing action with full tracking.
     */
    public static function executeHeal(string $subsystem, string $action, string $trigger, callable $fn, ?int $userId = null): array
    {
        // Cooldown: don't repeat same action within 5 minutes
        try {
            $recent = HealingAction::where('subsystem', $subsystem)
                ->where('action', $action)
                ->where('created_at', '>', now()->subMinutes(5))
                ->exists();
            if ($recent) return ['skipped' => true, 'reason' => 'cooldown'];
        } catch (\Throwable $e) {}

        $record = null;
        try {
            $record = HealingAction::create([
                'subsystem'    => $subsystem,
                'action'       => $action,
                'trigger'      => $trigger,
                'status'       => 'running',
                'triggered_by' => $userId ?? auth()->id(),
                'started_at'   => now(),
            ]);
        } catch (\Throwable $e) {}

        try {
            $result = $fn();

            $status = ($result['success'] ?? false) ? 'success' : 'failed';
            if ($record) {
                $record->update([
                    'status'       => $status,
                    'result'       => $result,
                    'completed_at' => now(),
                ]);
            }

            Log::info("Self-heal [{$subsystem}/{$action}]: {$status}", $result);
            return $result;
        } catch (\Throwable $e) {
            if ($record) {
                $record->update([
                    'status'       => 'failed',
                    'result'       => ['error' => $e->getMessage()],
                    'completed_at' => now(),
                ]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get a summary for the admin dashboard.
     */
    public static function summary(): array
    {
        $queueState = 'unknown';
        $schedulerState = 'unknown';
        $storageState = 'unknown';

        try {
            $latestQueue = QueueHealthSnapshot::orderByDesc('recorded_at')->first();
            if ($latestQueue) $queueState = $latestQueue->state;
        } catch (\Throwable $e) {}

        try {
            $status = \App\Models\StorageStatus::current();
            $storageState = $status->state ?? 'unknown';
        } catch (\Throwable $e) {}

        try {
            if (Schema::hasTable('scheduler_run_log')) {
                $last = SchedulerRunLog::orderByDesc('ran_at')->first();
                $mins = $last ? now()->diffInMinutes($last->ran_at) : 999;
                $schedulerState = $mins <= 10 ? 'healthy' : ($mins <= 30 ? 'degraded' : 'critical');
            }
        } catch (\Throwable $e) {}

        $recentActions = collect();
        try {
            $recentActions = HealingAction::recent(24)->orderByDesc('created_at')->limit(10)->get();
        } catch (\Throwable $e) {}

        return [
            'queue'     => $queueState,
            'scheduler' => $schedulerState,
            'storage'   => $storageState,
            'overall'   => self::overallState($queueState, $schedulerState, $storageState),
            'actions'   => $recentActions,
        ];
    }

    private static function overallState(string ...$states): string
    {
        if (in_array('critical', $states) || in_array('failed', $states)) return 'critical';
        if (in_array('degraded', $states) || in_array('lagging', $states) || in_array('stuck', $states)) return 'degraded';
        if (in_array('failover_active', $states)) return 'failover';
        if (in_array('unknown', $states)) return 'unknown';
        return 'healthy';
    }
}
