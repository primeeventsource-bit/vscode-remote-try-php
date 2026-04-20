<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('statement_deposits')) return;

        Schema::create('statement_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->unsignedSmallInteger('deposit_day')->nullable();
            $table->date('deposit_date')->nullable();
            $table->string('reference_number', 64)->nullable();
            $table->string('batch_id', 64)->nullable();
            $table->string('tran_code', 8)->nullable();
            $table->string('plan_code', 16)->nullable();
            $table->integer('sales_count')->default(0);
            $table->decimal('sales_amount', 14, 2)->default(0);
            $table->decimal('credits_amount', 14, 2)->default(0);
            $table->decimal('discount_paid', 14, 2)->default(0);
            $table->decimal('net_deposit', 14, 2)->default(0);
            $table->text('raw_row_text')->nullable();
            $table->timestamps();

            $table->index('statement_id');
            $table->index('deposit_date');
            $table->index('reference_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_deposits');
    }
};
