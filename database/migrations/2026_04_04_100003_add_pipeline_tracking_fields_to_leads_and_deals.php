<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pipeline tracking columns to leads and deals tables.
 * These provide fast current-state access alongside pipeline_events history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── LEADS: pipeline tracking fields ─────────────────────────
        Schema::table('leads', function (Blueprint $table) {
            $table->string('current_stage', 50)->nullable()->after('disposition')->index();
            $table->foreignId('transferred_by_user_id')->nullable()->after('current_stage')->constrained('users');
            $table->foreignId('transferred_to_user_id')->nullable()->after('transferred_by_user_id')->constrained('users');
            $table->timestamp('transferred_at')->nullable()->after('transferred_to_user_id');
            $table->timestamp('closer_received_at')->nullable()->after('transferred_at');
            $table->foreignId('closed_by_user_id')->nullable()->after('closer_received_at')->constrained('users');
            $table->timestamp('closed_at')->nullable()->after('closed_by_user_id');
            $table->foreignId('converted_to_deal_id')->nullable()->after('closed_at')->constrained('deals');
            $table->foreignId('sent_to_verification_by_user_id')->nullable()->after('converted_to_deal_id')->constrained('users');
            $table->timestamp('sent_to_verification_at')->nullable()->after('sent_to_verification_by_user_id');
            $table->foreignId('verification_received_by_user_id')->nullable()->after('sent_to_verification_at')->constrained('users');
            $table->timestamp('verification_received_at')->nullable()->after('verification_received_by_user_id');
            $table->string('final_outcome', 50)->nullable()->after('verification_received_at')->index();
            $table->timestamp('final_outcome_at')->nullable()->after('final_outcome');

            $table->index(['transferred_by_user_id', 'transferred_at']);
            $table->index(['transferred_to_user_id', 'transferred_at']);
            $table->index(['closed_by_user_id', 'closed_at']);
        });

        // ── DEALS: pipeline tracking fields ─────────────────────────
        Schema::table('deals', function (Blueprint $table) {
            // closer_user_id and verification_admin_user_id mirror existing
            // fronter/closer/assigned_admin but with explicit naming
            if (!Schema::hasColumn('deals', 'closer_user_id')) {
                $table->foreignId('closer_user_id')->nullable()->after('closer')->constrained('users');
            }
            if (!Schema::hasColumn('deals', 'verification_admin_user_id')) {
                $table->foreignId('verification_admin_user_id')->nullable()->after('assigned_admin')->constrained('users');
            }
            $table->string('charge_status', 30)->nullable()->after('charged_back')->index();
            $table->string('verification_status', 30)->nullable()->after('charge_status')->index();
            $table->foreignId('sent_to_verification_by_user_id')->nullable()->after('verification_status')->constrained('users');
            $table->timestamp('sent_to_verification_at')->nullable()->after('sent_to_verification_by_user_id');
            $table->timestamp('verification_received_at')->nullable()->after('sent_to_verification_at');
            $table->foreignId('charged_by_user_id')->nullable()->after('verification_received_at')->constrained('users');
            $table->timestamp('charged_at')->nullable()->after('charged_by_user_id');
            $table->boolean('is_green')->default(false)->after('charged_at')->index();

            $table->index(['closer_user_id', 'created_at']);
            $table->index(['verification_admin_user_id', 'verification_received_at']);
            $table->index(['charged_by_user_id', 'charged_at']);
        });

        // ── Backfill from existing data ─────────────────────────────
        // Sync closer_user_id from closer, verification_admin_user_id from assigned_admin
        \DB::statement('UPDATE deals SET closer_user_id = closer WHERE closer IS NOT NULL AND closer_user_id IS NULL');
        \DB::statement('UPDATE deals SET verification_admin_user_id = assigned_admin WHERE assigned_admin IS NOT NULL AND verification_admin_user_id IS NULL');
        \DB::statement("UPDATE deals SET is_green = 1, charge_status = 'charged' WHERE charged = 'yes' AND is_green = 0");
        \DB::statement("UPDATE deals SET charge_status = 'not_charged' WHERE status = 'cancelled' AND charge_status IS NULL");
        \DB::statement("UPDATE deals SET verification_status = 'charged' WHERE charged = 'yes' AND verification_status IS NULL");
        \DB::statement("UPDATE deals SET verification_status = 'pending' WHERE status = 'in_verification' AND verification_status IS NULL");
        \DB::statement("UPDATE deals SET charged_at = charged_date WHERE charged = 'yes' AND charged_at IS NULL AND charged_date IS NOT NULL");

        // Backfill lead pipeline fields from existing disposition data
        \DB::statement("
            UPDATE leads SET
                current_stage = 'transferred_to_closer',
                transferred_by_user_id = COALESCE(original_fronter, assigned_to),
                transferred_to_user_id = CAST(transferred_to AS BIGINT),
                transferred_at = updated_at
            WHERE disposition = 'Transferred to Closer'
                AND transferred_to IS NOT NULL
                AND ISNUMERIC(transferred_to) = 1
                AND current_stage IS NULL
        ");

        \DB::statement("
            UPDATE leads SET
                current_stage = 'converted_to_deal',
                final_outcome = 'deal_created',
                final_outcome_at = updated_at
            WHERE disposition = 'Converted to Deal'
                AND current_stage IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $cols = ['closer_user_id', 'verification_admin_user_id', 'charge_status',
                     'verification_status', 'sent_to_verification_by_user_id',
                     'sent_to_verification_at', 'verification_received_at',
                     'charged_by_user_id', 'charged_at', 'is_green'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('deals', $col)) {
                    // Drop foreign keys for _id columns
                    if (str_ends_with($col, '_id')) {
                        try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                    }
                }
            }
        });
        Schema::table('deals', function (Blueprint $table) {
            $cols = ['closer_user_id', 'verification_admin_user_id', 'charge_status',
                     'verification_status', 'sent_to_verification_by_user_id',
                     'sent_to_verification_at', 'verification_received_at',
                     'charged_by_user_id', 'charged_at', 'is_green'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('deals', $c));
            if (!empty($existing)) $table->dropColumn($existing);
        });

        Schema::table('leads', function (Blueprint $table) {
            $cols = ['current_stage', 'transferred_by_user_id', 'transferred_to_user_id',
                     'transferred_at', 'closer_received_at', 'closed_by_user_id', 'closed_at',
                     'converted_to_deal_id', 'sent_to_verification_by_user_id',
                     'sent_to_verification_at', 'verification_received_by_user_id',
                     'verification_received_at', 'final_outcome', 'final_outcome_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('leads', $col) && str_ends_with($col, '_id')) {
                    try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                }
            }
        });
        Schema::table('leads', function (Blueprint $table) {
            $cols = ['current_stage', 'transferred_by_user_id', 'transferred_to_user_id',
                     'transferred_at', 'closer_received_at', 'closed_by_user_id', 'closed_at',
                     'converted_to_deal_id', 'sent_to_verification_by_user_id',
                     'sent_to_verification_at', 'verification_received_by_user_id',
                     'verification_received_at', 'final_outcome', 'final_outcome_at'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('leads', $c));
            if (!empty($existing)) $table->dropColumn($existing);
        });
    }
};
