<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'source_file_name')) {
                $table->string('source_file_name', 255)->nullable()->after('source');
            }
        });

        // Add index only if missing. MySQL exposes SHOW INDEX; sqlite uses pragma.
        $driver = DB::getDriverName();
        $hasIndex = match ($driver) {
            'mysql' => collect(DB::select("SHOW INDEX FROM leads WHERE Key_name = 'idx_leads_source_file_name'"))->isNotEmpty(),
            'sqlite' => collect(DB::select("PRAGMA index_list('leads')"))->contains(fn ($i) => $i->name === 'idx_leads_source_file_name'),
            default => false,
        };
        if (!$hasIndex) {
            DB::statement('CREATE INDEX idx_leads_source_file_name ON leads(source_file_name)');
        }

        // Backfill: any lead still NULL gets 'Legacy Import' so the filter dropdown
        // includes them. We try to use the batch's original_filename first when
        // available, falling back to the literal 'Legacy Import'.
        // MySQL UPDATE...JOIN syntax — guarded so the migration runs on sqlite (dev/test).
        // On a fresh DB this is a no-op anyway (no rows to backfill).
        if ($driver === 'mysql') {
            DB::statement("
                UPDATE leads l
                LEFT JOIN lead_import_batches b ON b.id = l.import_batch_id
                SET l.source_file_name = COALESCE(b.original_filename, 'Legacy Import')
                WHERE l.source_file_name IS NULL
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        $hasIndex = match ($driver) {
            'mysql' => collect(DB::select("SHOW INDEX FROM leads WHERE Key_name = 'idx_leads_source_file_name'"))->isNotEmpty(),
            'sqlite' => collect(DB::select("PRAGMA index_list('leads')"))->contains(fn ($i) => $i->name === 'idx_leads_source_file_name'),
            default => false,
        };
        if ($hasIndex) {
            // MySQL: DROP INDEX name ON table. sqlite/pgsql: DROP INDEX name.
            DB::statement($driver === 'mysql'
                ? 'DROP INDEX idx_leads_source_file_name ON leads'
                : 'DROP INDEX idx_leads_source_file_name');
        }
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'source_file_name')) {
                $table->dropColumn('source_file_name');
            }
        });
    }
};
