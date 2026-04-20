<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('statement_fees')) return;

        Schema::create('statement_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->string('fee_description');
            $table->string('fee_category', 32)->default('misc');
            // discount, dispute, network, auth, monthly, compliance, reserve, misc
            $table->integer('quantity')->default(0);
            $table->decimal('basis_amount', 14, 2)->default(0);
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('fee_total', 14, 2)->default(0);
            $table->text('raw_row_text')->nullable();
            $table->timestamps();

            $table->index('statement_id');
            $table->index('fee_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_fees');
    }
};
