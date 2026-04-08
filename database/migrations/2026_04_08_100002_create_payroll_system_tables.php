<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // 1. PAYROLL SETTINGS
        // ═══════════════════════════════════════════════════════
        if (!Schema::hasTable('payroll_settings')) {
            Schema::create('payroll_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value');
                $table->string('setting_type', 50)->default('string');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════
        // 2. DEAL FINANCIALS
        // ═══════════════════════════════════════════════════════
        if (!Schema::hasTable('deal_financials')) {
            Schema::create('deal_financials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deal_id')->unique();

                // Percent snapshot
                $table->decimal('fronter_percent', 5, 2)->default(6.00);
                $table->decimal('closer_percent', 5, 2)->default(12.00);
                $table->decimal('admin_percent', 5, 2)->default(2.00);
                $table->decimal('processing_percent', 5, 2)->default(3.00);
                $table->decimal('reserve_percent', 5, 2)->default(3.00);
                $table->decimal('marketing_percent', 5, 2)->default(15.00);

                // Amount snapshot
                $table->decimal('gross_amount', 12, 2)->default(0);
                $table->decimal('collected_amount', 12, 2)->default(0);
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->decimal('chargeback_amount', 12, 2)->default(0);

                // Calculated commissions
                $table->decimal('fronter_commission', 12, 2)->default(0);
                $table->decimal('closer_commission', 12, 2)->default(0);
                $table->decimal('admin_commission', 12, 2)->default(0);

                // Business deductions
                $table->decimal('processing_fee', 12, 2)->default(0);
                $table->decimal('reserve_fee', 12, 2)->default(0);
                $table->decimal('marketing_cost', 12, 2)->default(0);

                // Company profit
                $table->decimal('company_net', 12, 2)->default(0);
                $table->decimal('company_net_percent', 8, 4)->default(0);

                // Adjustments
                $table->decimal('manual_adjustment', 12, 2)->default(0);
                $table->text('adjustment_reason')->nullable();

                // Flags
                $table->boolean('is_locked')->default(false);
                $table->boolean('is_disputed')->default(false);
                $table->boolean('is_reversed')->default(false);

                // Audit
                $table->timestamp('calculated_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
                $table->index('calculated_at');
                $table->index('approved_at');
                $table->index('is_locked');
                $table->index('is_disputed');
            });
        }

        // ═══════════════════════════════════════════════════════
        // 3. PAYROLL BATCHES
        // ═══════════════════════════════════════════════════════
        if (!Schema::hasTable('payroll_batches_v2')) {
            Schema::create('payroll_batches_v2', function (Blueprint $table) {
                $table->id();
                $table->string('batch_name', 150);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('batch_status', 50)->default('draft');

                $table->decimal('total_gross', 14, 2)->default(0);
                $table->decimal('total_commissions', 14, 2)->default(0);
                $table->decimal('total_processing', 14, 2)->default(0);
                $table->decimal('total_reserve', 14, 2)->default(0);
                $table->decimal('total_marketing', 14, 2)->default(0);
                $table->decimal('total_company_net', 14, 2)->default(0);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();

                $table->timestamp('approved_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('paid_at')->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('batch_status');
                $table->index(['period_start', 'period_end']);
            });
        }

        // ═══════════════════════════════════════════════════════
        // 4. PAYROLL BATCH ITEMS
        // ═══════════════════════════════════════════════════════
        if (!Schema::hasTable('payroll_batch_items')) {
            Schema::create('payroll_batch_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_batch_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role_code', 50);

                $table->decimal('gross_volume', 14, 2)->default(0);
                $table->integer('deal_count')->default(0);
                $table->decimal('base_commission', 12, 2)->default(0);
                $table->decimal('bonus_amount', 12, 2)->default(0);
                $table->decimal('hold_amount', 12, 2)->default(0);
                $table->decimal('deduction_amount', 12, 2)->default(0);
                $table->decimal('adjustment_amount', 12, 2)->default(0);
                $table->decimal('final_payout', 12, 2)->default(0);

                $table->string('payout_status', 50)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('payroll_batch_id')->references('id')->on('payroll_batches_v2')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['payroll_batch_id', 'user_id', 'role_code']);
            });
        }

        // ═══════════════════════════════════════════════════════
        // 5. PAYROLL BATCH DEALS
        // ═══════════════════════════════════════════════════════
        if (!Schema::hasTable('payroll_batch_deals')) {
            Schema::create('payroll_batch_deals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_batch_id');
                $table->unsignedBigInteger('deal_id');
                $table->unsignedBigInteger('deal_financial_id');
                $table->timestamps();

                $table->foreign('payroll_batch_id')->references('id')->on('payroll_batches_v2')->onDelete('cascade');
                $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
                $table->foreign('deal_financial_id')->references('id')->on('deal_financials')->onDelete('cascade');
                $table->unique(['payroll_batch_id', 'deal_id']);
            });
        }

        // ═══════════════════════════════════════════════════════
        // 6. FINANCE AUDITS
        // ═══════════════════════════════════════════════════════
        if (!Schema::hasTable('finance_audits')) {
            Schema::create('finance_audits', function (Blueprint $table) {
                $table->id();
                $table->string('auditable_type', 100);
                $table->unsignedBigInteger('auditable_id');
                $table->string('action', 100);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['auditable_type', 'auditable_id']);
                $table->index('action');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_batch_deals');
        Schema::dropIfExists('payroll_batch_items');
        Schema::dropIfExists('payroll_batches_v2');
        Schema::dropIfExists('deal_financials');
        Schema::dropIfExists('finance_audits');
        Schema::dropIfExists('payroll_settings');
    }
};
