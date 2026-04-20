<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('live_chargebacks')) return;

        Schema::create('live_chargebacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_account_id')->constrained('merchant_accounts')->cascadeOnDelete();
            $table->foreignId('processor_id')->nullable()->constrained('processors')->nullOnDelete();
            $table->string('reference_number', 64)->nullable();
            $table->string('card_brand', 32)->nullable();
            $table->string('reason_code', 32)->nullable();
            $table->string('case_number', 64)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 24)->default('open');
            // open, represented, won, lost, reversed, settled, monitoring
            $table->string('event_type', 32)->default('chargeback');
            $table->text('notes')->nullable();
            $table->foreignId('linked_reversal_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('dispute_date')->nullable();
            $table->date('deadline_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_account_id', 'status']);
            $table->index('dispute_date');
            $table->index('reference_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_chargebacks');
    }
};
