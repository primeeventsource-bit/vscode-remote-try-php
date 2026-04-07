<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_import_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_import_batch_id')->constrained('lead_import_batches');
            $table->unsignedInteger('row_number');
            $table->json('raw_row')->nullable();
            $table->string('reason');
            $table->string('failure_type', 30); // validation, duplicate, exception
            $table->unsignedBigInteger('matched_lead_id')->nullable();
            $table->string('duplicate_type', 20)->nullable();
            $table->string('duplicate_reason')->nullable();
            $table->json('matched_fields')->nullable();
            $table->string('resolution_status', 30)->default('pending'); // pending, imported, skipped, reviewed
            $table->timestamps();

            $table->index('lead_import_batch_id');
            $table->index('failure_type');
            $table->index('resolution_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_import_failures');
    }
};
