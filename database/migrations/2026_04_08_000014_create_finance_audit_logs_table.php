<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_audit_logs')) return;

        Schema::create('finance_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('auditable_type', 128);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 32); // created, updated, deleted, corrected, uploaded, parsed, reviewed
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_audit_logs');
    }
};
