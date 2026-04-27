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

        // Add index only if missing (Schema doesn't expose hasIndex cleanly; use raw query)
        $hasIndex = collect(DB::select("SHOW INDEX FROM leads WHERE Key_name = 'idx_leads_source_file_name'"))->isNotEmpty();
        if (!$hasIndex) {
            DB::statement('CREATE INDEX idx_leads_source_file_name ON leads(source_file_name)');
        }

        // Backfill: any lead still NULL gets 'Legacy Import' so the filter dropdown
        // includes them. We try to use the batch's original_filename first when
        // available, falling back to the literal 'Legacy Import'.
        DB::statement("
            UPDATE leads l
            LEFT JOIN lead_import_batches b ON b.id = l.import_batch_id
            SET l.source_file_name = COALESCE(b.original_filename, 'Legacy Import')
            WHERE l.source_file_name IS NULL
        ");
    }

    public function down(): void
    {
        $hasIndex = collect(DB::select("SHOW INDEX FROM leads WHERE Key_name = 'idx_leads_source_file_name'"))->isNotEmpty();
        if ($hasIndex) {
            DB::statement('DROP INDEX idx_leads_source_file_name ON leads');
        }
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'source_file_name')) {
                $table->dropColumn('source_file_name');
            }
        });
    }
};
