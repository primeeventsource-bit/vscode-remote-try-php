<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup of the `migrations` table surfaced by audit:schema.
 *
 * 1. Deduplicates the `create_sales_training_tables` row (appears twice
 *    — artefact of a failed-retry deploy; actual migration only ran once).
 * 2. Removes rows whose migration files were deleted — Atlas + Zoho
 *    feature removals, plus Laravel-core migrations that were renamed
 *    during framework upgrades. Leaving orphan rows causes
 *    `migrate:status` to emit "Migration not found" warnings and blocks
 *    anyone from ever reintroducing a file with the same name.
 *
 * Idempotent: DELETE statements filter by current state, so running this
 * twice is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Dedupe: keep the oldest row (lowest id), drop the rest.
        $dupeName = '2026_04_05_000009_create_sales_training_tables';
        $keepId = DB::table('migrations')
            ->where('migration', $dupeName)
            ->min('id');

        if ($keepId !== null) {
            DB::table('migrations')
                ->where('migration', $dupeName)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // 2. Drop orphan rows — files no longer exist in database/migrations.
        $orphans = [
            '2026_01_01_000100_create_api_tokens_table',
            '2026_03_30_235648_create_sessions_table',
            '2026_03_30_235650_create_cache_table',
            '2026_03_30_235652_create_jobs_table',
            '2026_03_30_235653_create_failed_jobs_table',
            '2026_03_31_015231_add_invite_fields_to_users_table',
            '2026_03_31_151500_add_remember_token_to_users_table',
            '2026_04_09_000001_create_atlas_tables',
            '2026_04_08_200001_create_zoho_tables',
        ];

        DB::table('migrations')->whereIn('migration', $orphans)->delete();
    }

    public function down(): void
    {
        // Not reversible — we have no record of what batch numbers the
        // deleted rows belonged to, and recreating them would be harmful.
    }
};
