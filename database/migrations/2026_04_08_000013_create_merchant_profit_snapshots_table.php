<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('merchant_profit_snapshots')) return;

        Schema::create('merchant_profit_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_account_id')->constrained('merchant_accounts')->cascadeOnDelete();
            $table->string('snapshot_month', 7); // YYYY-MM
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('credits', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('net_deposits', 14, 2)->default(0);
            $table->decimal('discount_fees', 14, 2)->default(0);
            $table->decimal('other_processor_fees', 14, 2)->default(0);
            $table->decimal('total_chargebacks', 14, 2)->default(0);
            $table->decimal('total_reversals', 14, 2)->default(0);
            $table->decimal('net_chargeback_loss', 14, 2)->default(0);
            $table->decimal('dispute_fees', 14, 2)->default(0);
            $table->decimal('payroll_cost', 14, 2)->default(0);
            $table->decimal('operating_expenses', 14, 2)->default(0);
            $table->decimal('adjustments', 14, 2)->default(0);
            $table->decimal('true_net_profit', 14, 2)->default(0);
            $table->decimal('profit_margin_pct', 8, 4)->default(0);
            $table->decimal('chargeback_ratio_pct', 8, 4)->default(0);
            $table->decimal('fee_to_volume_ratio_pct', 8, 4)->default(0);
            $table->decimal('reserve_balance', 14, 2)->default(0);
            $table->unsignedTinyInteger('mid_health_score')->default(0);
            $table->json('waterfall_json')->nullable(); // full waterfall breakdown
            $table->timestamps();

            $table->unique(['merchant_account_id', 'snapshot_month'], 'profit_mid_month_unique');
            $table->index('snapshot_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_profit_snapshots');
    }
};
