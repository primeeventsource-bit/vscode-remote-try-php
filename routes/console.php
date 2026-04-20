<?php

use Illuminate\Support\Facades\Schedule;

// ─── Scheduler Heartbeat (every minute — production monitoring) ─
Schedule::command('scheduler:heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

// ─── Stale call cleanup (every minute) ──────────────────────
Schedule::command('calls:cleanup --seconds=90')
    ->everyMinute()
    ->withoutOverlapping();

// ─── Presence ────────────────────────────────────────────────
Schedule::command('presence:recompute')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// ─── Lead duplicate scan (nightly off-peak) ──────────────────
Schedule::command('leads:scan-duplicates --chunk=500')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// ─── Expire stale API tokens ─────────────────────────────────
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('api_tokens')
        ->where('expires_at', '<', now())
        ->delete();
})->hourly();

// ─── Unified self-healing cycle (every 5 minutes) ───────────
// Replaces separate monitor:health + storage:health-check
// Runs queue + scheduler + storage health in one coordinated pass
Schedule::command('system:heal')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ─── System health checks (component-level, every 5 minutes) ─
Schedule::command('monitor:health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ─── Clean old storage events (weekly) ───────────────────────
Schedule::call(function () {
    try {
        $days = config('storage_resilience.retention_days_for_logs', 30);
        \Illuminate\Support\Facades\DB::table('storage_events')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    } catch (\Throwable $e) {}
})->weeklyOn(0, '04:30');

// ─── Clean old health check records (daily) ──────────────────
Schedule::call(function () {
    try {
        \Illuminate\Support\Facades\DB::table('system_health_checks')
            ->where('checked_at', '<', now()->subDays(7))
            ->delete();
        \Illuminate\Support\Facades\DB::table('scheduler_heartbeats')
            ->where('ran_at', '<', now()->subDays(7))
            ->delete();
    } catch (\Throwable $e) {}
})->dailyAt('04:00');

// ─── Weekly stats snapshot (Mondays 00:01) ───────────────────
// Snapshots the week that just ended. Running a snapshot for the
// current (now previous) week freezes its numbers so historical
// views always match reality even if a deal is later edited.
Schedule::call(function () {
    try {
        $prevWeekKey = \App\Models\WeeklyStatsSnapshot::weekKeyFor(now()->subWeek());
        app(\App\Services\WeeklyStatsService::class)->snapshotWeek($prevWeekKey);
        \Illuminate\Support\Facades\Log::info("Weekly stats snapshot saved: {$prevWeekKey}");
    } catch (\Throwable $e) {
        report($e);
    }
})->weeklyOn(1, '00:01')->name('weekly-stats-snapshot')->withoutOverlapping();

// ─── Nightly running snapshot of the current week (23:58) ────
// Ensures current-week data is always recoverable even if the
// Monday job misses; updates the current week row in place.
Schedule::call(function () {
    try {
        $currentKey = \App\Models\WeeklyStatsSnapshot::weekKeyFor(now());
        app(\App\Services\WeeklyStatsService::class)->snapshotWeek($currentKey);
    } catch (\Throwable $e) {
        report($e);
    }
})->dailyAt('23:58')->name('nightly-stats-backup')->withoutOverlapping();
