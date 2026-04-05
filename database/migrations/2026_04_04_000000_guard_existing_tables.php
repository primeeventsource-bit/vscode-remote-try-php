<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guard migration: if tables already exist from previous partial runs,
 * insert the migration name into the migrations table so Laravel skips them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guards = [
            'client_audit_logs' => '2026_04_04_000002_create_client_audit_logs_table',
            'pipeline_events' => '2026_04_04_100001_create_pipeline_events_table',
            'crm_notes' => '2026_04_05_000001_create_crm_notes_table',
            'chargeback_cases' => '2026_04_05_000002_create_chargeback_cases_and_evidence_tables',
            'video_rooms' => '2026_04_05_000005_create_video_call_tables',
            'onboarding_flows' => '2026_04_05_000008_create_onboarding_tables',
            'objection_library' => '2026_04_05_000009_create_sales_training_tables',
            'sales_scripts' => '2026_04_05_000010_create_sales_scripts_training_targets_tables',
            'ai_prompt_templates' => '2026_04_05_000011_create_ai_engine_tables',
        ];

        foreach ($guards as $table => $migration) {
            if (Schema::hasTable($table)) {
                // Table exists — mark migration as already run so Laravel skips it
                $already = DB::table('migrations')->where('migration', $migration)->exists();
                if (!$already) {
                    DB::table('migrations')->insert([
                        'migration' => $migration,
                        'batch' => 1,
                    ]);
                }
            }
        }

        // Also guard the alter-table migrations
        $alterGuards = [
            '2026_04_04_000003_secure_card_data_on_deals' => 'card_last4',
            '2026_04_04_100002_backfill_pipeline_events_from_existing_data' => null,
            '2026_04_04_100003_add_pipeline_tracking_fields_to_leads_and_deals' => 'current_stage',
            '2026_04_05_000003_add_auto_task_fields_to_tasks_table' => 'auto_created',
            '2026_04_05_000004_add_avatar_emoji_fields' => 'avatar_emoji',
            '2026_04_05_000006_add_presence_fields_to_users_table' => 'presence_status',
            '2026_04_05_000007_add_chat_id_to_video_rooms' => null,
        ];

        foreach ($alterGuards as $migration => $checkColumn) {
            if ($checkColumn && Schema::hasColumn('deals', $checkColumn)) {
                $already = DB::table('migrations')->where('migration', $migration)->exists();
                if (!$already) {
                    DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
                }
            } elseif ($checkColumn && Schema::hasColumn('users', $checkColumn)) {
                $already = DB::table('migrations')->where('migration', $migration)->exists();
                if (!$already) {
                    DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
                }
            } elseif ($checkColumn && Schema::hasColumn('tasks', $checkColumn)) {
                $already = DB::table('migrations')->where('migration', $migration)->exists();
                if (!$already) {
                    DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
                }
            }
        }
    }

    public function down(): void
    {
        // Nothing to reverse
    }
};
