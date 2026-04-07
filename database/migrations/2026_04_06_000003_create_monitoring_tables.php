<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Health Checks ────────────────────────────────────
        if (! Schema::hasTable('system_health_checks')) {
            Schema::create('system_health_checks', function (Blueprint $table) {
                $table->id();
                $table->string('component', 50)->index(); // app, database, queue, storage, chat, security, scheduler
                $table->string('status', 20)->index();    // healthy, degraded, critical, unknown
                $table->json('details')->nullable();
                $table->integer('response_time_ms')->nullable();
                $table->timestamp('checked_at')->useCurrent();

                $table->index('checked_at');
            });
        }

        // ── Incidents ────────────────────────────────────────
        if (! Schema::hasTable('system_incidents')) {
            Schema::create('system_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('component', 50)->index();
                $table->string('severity', 20)->index();  // warning, critical, system_breaking
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->json('context')->nullable();
                $table->string('status', 20)->default('open')->index(); // open, acknowledged, resolved, auto_resolved
                $table->string('fingerprint', 64)->nullable()->index(); // dedup key
                $table->foreignId('assigned_to')->nullable()->constrained('users');
                $table->foreignId('resolved_by')->nullable()->constrained('users');
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
            });
        }

        // ── Recovery Actions ─────────────────────────────────
        if (! Schema::hasTable('system_recovery_actions')) {
            Schema::create('system_recovery_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incident_id')->nullable()->constrained('system_incidents');
                $table->string('action', 100);             // retry_failed_jobs, rebuild_unread, fix_storage_link, etc.
                $table->string('status', 20)->default('pending'); // pending, running, success, failed, skipped
                $table->boolean('requires_approval')->default(false);
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->json('result')->nullable();
                $table->integer('retry_count')->default(0);
                $table->integer('max_retries')->default(3);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('cooldown_until')->nullable();
                $table->timestamps();
            });
        }

        // ── Queue Metrics ────────────────────────────────────
        if (! Schema::hasTable('queue_metrics')) {
            Schema::create('queue_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('queue', 50)->default('default');
                $table->integer('pending_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->integer('processed_last_hour')->default(0);
                $table->integer('avg_wait_seconds')->nullable();
                $table->timestamp('recorded_at')->useCurrent();

                $table->index(['queue', 'recorded_at']);
            });
        }

        // ── Scheduler Heartbeats ─────────────────────────────
        if (! Schema::hasTable('scheduler_heartbeats')) {
            Schema::create('scheduler_heartbeats', function (Blueprint $table) {
                $table->id();
                $table->string('command', 255);
                $table->string('status', 20)->default('success'); // success, failed
                $table->integer('duration_ms')->nullable();
                $table->text('output')->nullable();
                $table->timestamp('ran_at')->useCurrent();

                $table->index('ran_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_heartbeats');
        Schema::dropIfExists('queue_metrics');
        Schema::dropIfExists('system_recovery_actions');
        Schema::dropIfExists('system_incidents');
        Schema::dropIfExists('system_health_checks');
    }
};
