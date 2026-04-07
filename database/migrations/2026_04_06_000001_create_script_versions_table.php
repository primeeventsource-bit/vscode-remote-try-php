<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('script_versions')) return;

        Schema::create('script_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained('sales_scripts');
            $table->integer('version_number')->default(1);
            $table->string('title_snapshot', 255);
            $table->longText('body_snapshot');
            $table->string('content_hash', 64)->nullable();
            $table->unsignedInteger('character_count')->default(0);
            $table->string('source_type', 50)->nullable(); // manual, pdf, import
            $table->string('source_filename', 255)->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        // Expand sales_scripts if needed
        if (! Schema::hasColumn('sales_scripts', 'content_hash')) {
            Schema::table('sales_scripts', function (Blueprint $table) {
                $table->string('content_hash', 64)->nullable()->after('content');
                $table->unsignedInteger('character_count')->default(0)->after('content_hash');
                $table->unsignedInteger('version_number')->default(1)->after('character_count');
                $table->string('source_type', 50)->nullable()->after('version_number');
                $table->string('source_filename', 255)->nullable()->after('source_type');
                $table->foreignId('updated_by')->nullable()->after('created_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('script_versions');

        if (Schema::hasColumn('sales_scripts', 'content_hash')) {
            Schema::table('sales_scripts', function (Blueprint $table) {
                $table->dropColumn(['content_hash', 'character_count', 'version_number', 'source_type', 'source_filename', 'updated_by']);
            });
        }
    }
};
