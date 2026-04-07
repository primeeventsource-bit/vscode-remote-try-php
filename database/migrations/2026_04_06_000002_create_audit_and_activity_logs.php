<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit log — tracks sensitive admin actions
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100)->index();       // e.g. script.updated, user.role_changed, payroll.finalized
                $table->string('target_type', 100)->nullable(); // e.g. App\Models\SalesScript
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['target_type', 'target_id']);
                $table->index('created_at');
            });
        }

        // Activity log — tracks CRM workflow events (lead assigned, deal closed, etc.)
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject_type', 100)->index(); // Lead, Deal, Client, etc.
                $table->unsignedBigInteger('subject_id');
                $table->string('event', 100)->index();        // created, updated, status_changed, assigned, transferred
                $table->json('properties')->nullable();        // any extra context
                $table->timestamp('created_at')->useCurrent();

                $table->index(['subject_type', 'subject_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('audit_logs');
    }
};
