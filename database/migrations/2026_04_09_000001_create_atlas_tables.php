<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('atlas_leads')) {
            Schema::create('atlas_leads', function (Blueprint $table) {
                $table->id();
                $table->string('grantee');
                $table->string('grantor');
                $table->string('county', 100)->nullable();
                $table->string('state', 2)->nullable();
                $table->date('deed_date')->nullable();
                $table->text('address')->nullable();
                $table->string('instrument', 100)->nullable();
                $table->string('deed_type', 100)->nullable();
                $table->string('phone_1', 20)->nullable();
                $table->string('phone_2', 20)->nullable();
                $table->string('phone_3', 20)->nullable();
                $table->enum('phone_confidence', ['high', 'medium', 'low', 'none'])->nullable();
                $table->text('phone_sources')->nullable();
                $table->enum('status', ['new', 'searched', 'traced', 'imported'])->default('new');
                $table->enum('source', ['manual', 'ai-text', 'ai-pdf', 'ai-phone'])->default('manual');
                $table->string('source_filename')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index(['county', 'state']);
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('atlas_parse_logs')) {
            Schema::create('atlas_parse_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('parse_type', ['text', 'pdf', 'phone']);
                $table->string('county', 100)->nullable();
                $table->string('state', 2)->nullable();
                $table->integer('leads_found')->default(0);
                $table->integer('leads_imported')->default(0);
                $table->integer('files_processed')->default(0);
                $table->text('raw_input_preview')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_parse_logs');
        Schema::dropIfExists('atlas_leads');
    }
};
