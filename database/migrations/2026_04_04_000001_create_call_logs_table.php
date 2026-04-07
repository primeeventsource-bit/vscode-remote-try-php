<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('call_logs')) {
            Schema::create('call_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('callable_type')->nullable();
                $table->unsignedBigInteger('callable_id')->nullable();
                $table->string('record_type', 50)->nullable();
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('raw_phone');
                $table->string('normalized_phone')->nullable();
                $table->string('extension', 20)->nullable();
                $table->string('launch_method', 30)->default('tel');
                $table->string('generated_href')->nullable();
                $table->string('status', 20)->default('initiated');
                $table->string('outcome', 30)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('initiated_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'initiated_at']);
                $table->index(['callable_type', 'callable_id']);
                $table->index(['record_type', 'record_id']);
                $table->index('normalized_phone');
                $table->index('status');
                $table->index('outcome');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
