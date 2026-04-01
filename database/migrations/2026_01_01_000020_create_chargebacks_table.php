<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('chargebacks')) {
            return;
        }

        Schema::create('chargebacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('merchant_account_id')->nullable()->constrained('merchant_accounts')->nullOnDelete();
            $table->foreignId('processor_id')->nullable()->constrained('processors')->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();

            $table->string('dispute_reference_number')->nullable();
            $table->decimal('chargeback_amount', 12, 2)->default(0);
            $table->decimal('original_transaction_amount', 12, 2)->nullable();
            $table->string('currency', 8)->default('USD');

            $table->string('status', 40)->default('pending');
            $table->string('reason_code', 80)->nullable();
            $table->string('reason_description')->nullable();
            $table->string('card_brand', 40)->nullable();
            $table->string('payment_method', 40)->nullable();

            $table->date('dispute_date')->nullable();
            $table->date('deadline_date')->nullable();
            $table->dateTime('response_submitted_at')->nullable();
            $table->dateTime('resolved_at')->nullable();

            $table->string('outcome', 40)->default('pending');
            $table->boolean('refunded_before_dispute')->default(false);
            $table->string('prevention_source')->nullable();
            $table->string('source_system')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('dispute_date');
            $table->index('status');
            $table->index('processor_id');
            $table->index('sales_rep_id');
            $table->index('merchant_account_id');
            $table->index('reason_code');
            $table->index('card_brand');
            $table->index('deal_id');
            $table->index('transaction_id');
            $table->index('payment_method');
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargebacks');
    }
};
