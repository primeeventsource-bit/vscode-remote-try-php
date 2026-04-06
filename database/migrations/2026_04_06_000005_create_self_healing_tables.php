<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Scheduler Run Log — tracks every scheduled command execution ──
        if (! Schema::hasTable('scheduler_run_log')) {
            Schema::create('scheduler_run_log', function (Blueprint $table) {
                $table->id();
                $table->string('command', 255);
                $table->string('status', 20)->default('success'); // success, failed, skipped
                $table->integer('duration_ms')->nullable();
                $table->text('output')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('expected_at')->nullable();    // when it should have run
                $table->timestamp('ran_at')->useCurrent();

                $table->index(['command', 'ran_at']);
            });
        }

        // ── Queue Health Snapshots — periodic queue state ──
        if (! Schema::hasTable('queue_health_snapshots')) {
            Schema::create('queue_health_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('queue', 50)->default('default');
                $table->string('connection', 50)->default('database');
                $table->integer('pending_jobs')->default(0);
                $table->integer('failed_jobs')->default(0);
                $table->integer('processed_last_5min')->default(0);
                $table->integer('oldest_pending_seconds')->nullable();
                $table->string('state', 20)->default('healthy'); // healthy, lagging, stuck, down
                $table->timestamp('recorded_at')->useCurrent();

                $table->index('recorded_at');
            });
        }

        // ── Healing Actions — every auto/manual recovery attempt ──
        if (! Schema::hasTable('healing_actions')) {
            Schema::create('healing_actions', function (Blueprint $table) {
                $table->id();
                $table->string('subsystem', 50);      // queue, scheduler, storage
                $table->string('action', 100);          // retry_failed_jobs, fix_storage_link, restart_stuck, etc.
                $table->string('trigger', 100);         // auto_health_check, manual_admin, incident_recovery
                $table->string('status', 20)->default('pending'); // pending, running, success, failed, skipped
                $table->json('input')->nullable();       // what was passed in
                $table->json('result')->nullable();      // what happened
                $table->integer('retry_count')->default(0);
                $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['subsystem', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('healing_actions');
        Schema::dropIfExists('queue_health_snapshots');
        Schema::dropIfExists('scheduler_run_log');
    }
};
