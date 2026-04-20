<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('statement_plan_summaries')) return;

        Schema::create('statement_plan_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->string('card_brand', 32);    // VS, MC, AM, DS, DB, etc.
            $table->string('plan_code', 16)->nullable();
            $table->integer('sales_count')->default(0);
            $table->decimal('sales_amount', 14, 2)->default(0);
            $table->integer('credit_count')->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('average_ticket', 10, 2)->default(0);
            $table->decimal('discount_rate', 8, 4)->default(0);
            $table->decimal('discount_due', 14, 2)->default(0);
            $table->timestamps();

            $table->index('statement_id');
            $table->index('card_brand');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_plan_summaries');
    }
};
