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
                $table->string('grantee', 255);
                $table->string('grantor', 255)->nullable();
                $table->string('county', 100)->nullable();
                $table->string('state', 2)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('zip', 10)->nullable();
                $table->date('deed_date')->nullable();
                $table->text('address')->nullable();
                $table->string('instrument', 100)->nullable();
                $table->string('deed_type', 100)->nullable();

                // Phone fields
                $table->string('existing_phone', 20)->nullable();
                $table->string('phone_1', 20)->nullable();
                $table->string('phone_1_type', 20)->nullable();
                $table->string('phone_2', 20)->nullable();
                $table->string('phone_2_type', 20)->nullable();
                $table->string('phone_3', 20)->nullable();
                $table->string('phone_3_type', 20)->nullable();
                $table->string('phone_4', 20)->nullable();
                $table->string('phone_4_type', 20)->nullable();
                $table->string('phone_5', 20)->nullable();
                $table->string('phone_5_type', 20)->nullable();
                $table->enum('phone_confidence', ['high', 'medium', 'low', 'none'])->nullable();

                // Email fields
                $table->string('email_1', 255)->nullable();
                $table->string('email_2', 255)->nullable();
                $table->string('email_3', 255)->nullable();

                // Metadata
                $table->enum('status', ['new', 'searched', 'traced', 'imported'])->default('new');
                $table->enum('source', ['manual', 'sheets', 'ai-text', 'ai-pdf'])->default('manual');
                $table->string('source_filename', 255)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('traced_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index(['county', 'state']);
                $table->index('created_at');
                $table->index('traced_at');
            });
        }

        if (!Schema::hasTable('atlas_parse_logs')) {
            Schema::create('atlas_parse_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('parse_type', ['sheets', 'skip-trace', 'ai-text', 'ai-pdf']);
                $table->string('county', 100)->nullable();
                $table->string('state', 2)->nullable();
                $table->integer('leads_found')->default(0);
                $table->integer('leads_imported')->default(0);
                $table->integer('leads_traced')->default(0);
                $table->integer('files_processed')->default(0);
                $table->decimal('cost_estimate', 8, 2)->nullable();
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
