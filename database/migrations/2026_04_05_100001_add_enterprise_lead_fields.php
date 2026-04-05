<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Soft deletes — preserve data, never lose leads
            if (!Schema::hasColumn('leads', 'deleted_at')) {
                $table->softDeletes();
            }

            // Email field for duplicate detection + deal conversion prep
            if (!Schema::hasColumn('leads', 'email')) {
                $table->string('email', 191)->nullable()->after('resort_location');
            }

            // Imported_at — distinct from created_at for manually created leads
            if (!Schema::hasColumn('leads', 'imported_at')) {
                $table->timestamp('imported_at')->nullable()->after('source');
            }

            // Import batch reference
            if (!Schema::hasColumn('leads', 'import_batch_id')) {
                $table->unsignedBigInteger('import_batch_id')->nullable()->after('imported_at');
            }
        });

        // Performance indexes for duplicate detection + filtering at scale
        Schema::table('leads', function (Blueprint $table) {
            // Phone indexes for duplicate matching
            try { $table->index('phone1'); } catch (\Throwable $e) {}
            try { $table->index('phone2'); } catch (\Throwable $e) {}

            // Email index for duplicate matching
            try { $table->index('email'); } catch (\Throwable $e) {}

            // Owner name index for duplicate matching
            try { $table->index('owner_name'); } catch (\Throwable $e) {}

            // Imported_at for age filtering
            try { $table->index('imported_at'); } catch (\Throwable $e) {}

            // Soft delete index
            try { $table->index('deleted_at'); } catch (\Throwable $e) {}

            // Import batch lookup
            try { $table->index('import_batch_id'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropSoftDeletes();
            if (Schema::hasColumn('leads', 'email')) $table->dropColumn('email');
            if (Schema::hasColumn('leads', 'imported_at')) $table->dropColumn('imported_at');
            if (Schema::hasColumn('leads', 'import_batch_id')) $table->dropColumn('import_batch_id');
        });
    }
};
