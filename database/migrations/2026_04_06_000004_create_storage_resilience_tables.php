<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storage_statuses')) {
            Schema::create('storage_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('primary_disk', 50);
                $table->string('fallback_disk', 50);
                $table->string('active_disk', 50);
                $table->string('state', 30)->default('healthy'); // healthy, degraded, failed, failover_active
                $table->boolean('primary_healthy')->default(true);
                $table->boolean('fallback_healthy')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->unsignedInteger('recovery_count')->default(0);
                $table->unsignedInteger('primary_latency_ms')->nullable();
                $table->unsignedInteger('fallback_latency_ms')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('last_failover_at')->nullable();
                $table->timestamp('last_recovery_at')->nullable();
                $table->string('forced_disk', 50)->nullable(); // admin override
                $table->json('details')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('storage_events')) {
            Schema::create('storage_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_type', 50)->index();
                $table->string('disk', 50)->nullable();
                $table->string('previous_disk', 50)->nullable();
                $table->string('new_disk', 50)->nullable();
                $table->string('severity', 20)->default('info'); // info, warning, critical
                $table->string('message', 500);
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_events');
        Schema::dropIfExists('storage_statuses');
    }
};
