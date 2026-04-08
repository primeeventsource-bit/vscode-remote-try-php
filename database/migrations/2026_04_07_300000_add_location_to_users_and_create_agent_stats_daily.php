<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Add location column to users ────────────────
        if (!Schema::hasColumn('users', 'location')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('location', 20)->nullable()->after('role')
                    ->comment('US or Panama — derived from role suffix');
                $table->index(['role', 'location']);
            });

            // Backfill location from existing role suffixes
            DB::table('users')->whereIn('role', ['fronter_panama', 'closer_panama'])->update(['location' => 'Panama']);
            DB::table('users')->whereIn('role', ['fronter', 'closer'])->update(['location' => 'US']);
            DB::table('users')->whereIn('role', ['admin', 'master_admin', 'admin_limited'])->update(['location' => 'US']);
        }

        // ── Step 2: Create agent_stats_daily table ──────────────
        if (!Schema::hasTable('agent_stats_daily')) {
            Schema::create('agent_stats_daily', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('role', 30);
                $table->string('location', 20)->nullable();
                $table->date('stat_date');

                // Fronter metrics
                $table->unsignedInteger('leads_assigned')->default(0);
                $table->unsignedInteger('leads_contacted')->default(0);
                $table->unsignedInteger('leads_qualified')->default(0);
                $table->unsignedInteger('leads_not_interested')->default(0);
                $table->unsignedInteger('leads_transferred')->default(0);
                $table->unsignedInteger('avg_first_contact_seconds')->default(0);

                // Closer metrics
                $table->unsignedInteger('deals_received')->default(0);
                $table->unsignedInteger('deals_closed')->default(0);
                $table->unsignedInteger('deals_lost')->default(0);
                $table->decimal('revenue', 12, 2)->default(0);
                $table->decimal('avg_deal_value', 10, 2)->default(0);

                // Shared metrics
                $table->unsignedInteger('activity_count')->default(0);
                $table->unsignedInteger('tasks_completed')->default(0);
                $table->unsignedInteger('calls_made')->default(0);
                $table->unsignedInteger('sms_sent')->default(0);
                $table->unsignedInteger('follow_up_count')->default(0);
                $table->unsignedInteger('follow_up_on_time')->default(0);

                // AI scores
                $table->decimal('notes_quality_score', 5, 2)->nullable();
                $table->decimal('objection_handling_score', 5, 2)->nullable();

                $table->timestamps();

                $table->unique(['user_id', 'stat_date']);
                $table->index(['stat_date', 'role', 'location']);
                $table->index(['user_id', 'stat_date']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_stats_daily');

        if (Schema::hasColumn('users', 'location')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role', 'location']);
                $table->dropColumn('location');
            });
        }
    }
};
