<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_duplicates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('duplicate_lead_id');
            $table->string('duplicate_type', 20); // exact, possible
            $table->string('duplicate_reason');
            $table->json('matched_fields')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('review_status', 30)->default('pending'); // pending, kept_both, deleted_duplicate, merged, ignored
            $table->timestamps();

            $table->index('lead_id');
            $table->index('duplicate_lead_id');
            $table->index('review_status');
            $table->index('duplicate_type');
            $table->index(['lead_id', 'duplicate_lead_id']);
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_duplicates');
    }
};
