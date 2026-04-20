<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('statement_chargebacks')) return;

        Schema::create('statement_chargebacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->unsignedSmallInteger('chargeback_day')->nullable();
            $table->date('chargeback_date')->nullable();
            $table->string('reference_number', 64)->nullable();
            $table->string('tran_code', 8)->nullable();
            $table->string('card_brand', 32)->nullable();
            $table->string('reason_code', 32)->nullable();
            $table->string('case_number', 64)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('event_type', 32)->default('chargeback');
            // chargeback, reversal, representment_credit, retrieval_request
            $table->boolean('recovered_flag')->default(false);
            $table->foreignId('linked_chargeback_id')->nullable()->constrained('statement_chargebacks')->nullOnDelete();
            $table->foreignId('linked_reversal_id')->nullable()->constrained('statement_chargebacks')->nullOnDelete();
            $table->unsignedTinyInteger('matching_confidence')->default(0);
            $table->text('raw_row_text')->nullable();
            $table->timestamps();

            $table->index('statement_id');
            $table->index('event_type');
            $table->index('reference_number');
            $table->index('chargeback_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_chargebacks');
    }
};
