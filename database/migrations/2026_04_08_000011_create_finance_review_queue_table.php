<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_review_queue')) return;

        Schema::create('finance_review_queue', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->string('field_name', 64);
            $table->string('section', 32); // header, deposits, chargebacks, fees, reserves, totals
            $table->text('extracted_value')->nullable();
            $table->text('corrected_value')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('pending'); // pending, corrected, accepted, dismissed
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['statement_id', 'status']);
            $table->index('section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_review_queue');
    }
};
