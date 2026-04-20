<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_manual_expenses')) return;

        Schema::create('finance_manual_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_account_id')->nullable()->constrained('merchant_accounts')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category', 48); // software, rent, advertising, chargeback_service, phone, contractor, bank_fees, misc
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_date', 'category']);
            $table->index('merchant_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_manual_expenses');
    }
};
