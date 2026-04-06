<?php

use Illuminate\Support\Facades\Schedule;

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
