<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_adjustments')) return;

        Schema::create('finance_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_account_id')->nullable()->constrained('merchant_accounts')->nullOnDelete();
            $table->string('adjustment_month', 7); // YYYY-MM
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0); // positive = add, negative = subtract
            $table->string('type', 24)->default('manual'); // manual, correction, write_off
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('adjustment_month');
            $table->index('merchant_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_adjustments');
    }
};
