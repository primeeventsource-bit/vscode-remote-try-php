<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->string('event_type', 50)->index();
            $table->string('from_stage', 50)->nullable();
            $table->string('to_stage', 50)->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_role', 50)->nullable();
            $table->string('target_role', 50)->nullable();
            $table->boolean('success_flag')->default(true);
            $table->string('outcome', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('event_at')->index();
            $table->timestamps();

            $table->index(['source_user_id', 'event_type', 'event_at']);
            $table->index(['target_user_id', 'event_type', 'event_at']);
            $table->index(['performed_by_user_id', 'event_type', 'event_at']);
            $table->index(['event_type', 'success_flag', 'event_at']);
        });

        // Add lead_id to deals for proper FK link
        if (!Schema::hasColumn('deals', 'lead_id')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->foreignId('lead_id')->nullable()->after('id')->constrained('leads')->nullOnDelete();
                $table->index(['lead_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deals', 'lead_id')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->dropForeign(['lead_id']);
                $table->dropColumn('lead_id');
            });
        }
        Schema::dropIfExists('pipeline_events');
    }
};
