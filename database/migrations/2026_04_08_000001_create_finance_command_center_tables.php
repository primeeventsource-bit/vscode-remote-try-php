<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // 1. MERCHANT ACCOUNTS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('mid_number')->unique();
            $table->string('processor_name');
            $table->string('gateway_name')->nullable();
            $table->string('descriptor')->nullable();
            $table->string('business_name')->nullable();
            $table->string('account_status')->default('active'); // active, suspended, closed, pending
            $table->string('currency', 10)->nullable()->default('USD');
            $table->string('timezone')->nullable()->default('America/New_York');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('processor_name');
            $table->index('is_active');
        });

        // ═══════════════════════════════════════════════════════
        // 2. STATEMENT UPLOADS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_statement_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_account_id')->nullable();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('detected_processor')->nullable();
            $table->string('detected_statement_type')->nullable();
            $table->string('processing_status')->default('pending'); // pending, processing, parsed, preview, imported, failed
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('merchant_account_id')->references('id')->on('merchant_accounts')->onDelete('set null');
            $table->index('processing_status');
            $table->index('uploaded_at');
        });

        // ═══════════════════════════════════════════════════════
        // 3. STATEMENT SUMMARIES
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_statement_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_statement_upload_id');
            $table->unsignedBigInteger('merchant_account_id');
            $table->date('statement_start_date')->nullable();
            $table->date('statement_end_date')->nullable();
            $table->decimal('gross_volume', 14, 2)->nullable();
            $table->decimal('net_volume', 14, 2)->nullable();
            $table->decimal('refunds_total', 14, 2)->nullable();
            $table->decimal('chargebacks_total', 14, 2)->nullable();
            $table->decimal('fees_total', 14, 2)->nullable();
            $table->decimal('reserves_total', 14, 2)->nullable();
            $table->decimal('payouts_total', 14, 2)->nullable();
            $table->decimal('ending_balance', 14, 2)->nullable();
            $table->json('raw_summary_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_statement_upload_id')->references('id')->on('merchant_statement_uploads')->onDelete('cascade');
            $table->foreign('merchant_account_id')->references('id')->on('merchant_accounts')->onDelete('cascade');
            $table->index(['merchant_account_id', 'statement_start_date']);
        });

        // ═══════════════════════════════════════════════════════
        // 4. STATEMENT LINE ITEMS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_statement_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_statement_upload_id');
            $table->unsignedBigInteger('merchant_account_id')->nullable();
            $table->string('line_type'); // transaction, chargeback, fee, reserve_hold, reserve_release, payout, deposit, adjustment
            $table->string('external_reference')->nullable();
            $table->date('transaction_date')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('mapped_status')->nullable();
            $table->json('raw_line_json')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamps();

            $table->foreign('merchant_statement_upload_id')->references('id')->on('merchant_statement_uploads')->onDelete('cascade');
            $table->index(['line_type', 'needs_review']);
            $table->index('transaction_date');
        });

        // ═══════════════════════════════════════════════════════
        // 5. IMPORT BATCHES
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_statement_upload_id')->nullable();
            $table->unsignedBigInteger('merchant_account_id')->nullable();
            $table->string('import_type'); // full, transactions, chargebacks, fees, mixed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('merchant_statement_upload_id')->references('id')->on('merchant_statement_uploads')->onDelete('set null');
            $table->index('status');
        });

        // ═══════════════════════════════════════════════════════
        // 6. IMPORT FAILURES
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_import_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_import_batch_id');
            $table->unsignedInteger('row_number')->nullable();
            $table->string('error_type');
            $table->text('error_message');
            $table->json('row_data_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_import_batch_id')->references('id')->on('merchant_import_batches')->onDelete('cascade');
        });

        // ═══════════════════════════════════════════════════════
        // 7. MERCHANT TRANSACTIONS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_account_id');
            $table->unsignedBigInteger('statement_upload_id')->nullable();
            $table->string('external_transaction_id')->nullable();
            $table->string('order_reference')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('card_brand', 30)->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('descriptor')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->nullable()->default('USD');
            $table->string('transaction_status')->default('approved'); // approved, declined, pending, settled, refunded, partial_refund, reversed, chargeback
            $table->string('payment_status')->nullable();
            $table->string('refund_status')->nullable();
            $table->string('transaction_type')->nullable(); // sale, auth, capture, refund, void
            $table->date('transaction_date');
            $table->string('source_type')->nullable(); // statement_import, manual, api
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->json('raw_data_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_account_id')->references('id')->on('merchant_accounts')->onDelete('cascade');
            $table->foreign('statement_upload_id')->references('id')->on('merchant_statement_uploads')->onDelete('set null');
            $table->index(['merchant_account_id', 'transaction_date']);
            $table->index('transaction_status');
            $table->index('external_transaction_id');
            $table->index('card_brand');
        });

        // ═══════════════════════════════════════════════════════
        // 8. MERCHANT CHARGEBACKS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_chargebacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_account_id');
            $table->unsignedBigInteger('merchant_transaction_id')->nullable();
            $table->unsignedBigInteger('statement_upload_id')->nullable();
            $table->string('external_chargeback_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->nullable()->default('USD');
            $table->string('card_brand', 30)->nullable();
            $table->string('reason_code', 50)->nullable();
            $table->text('reason_description')->nullable();
            $table->string('processor_status')->nullable();
            $table->string('internal_status')->default('new'); // new, open, pending_response, evidence_submitted, won, lost, reversed, closed
            $table->string('evidence_status')->nullable();
            $table->date('opened_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('resolved_at')->nullable();
            $table->string('outcome')->nullable(); // won, lost, reversed
            $table->text('notes')->nullable();
            $table->json('raw_data_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_account_id')->references('id')->on('merchant_accounts')->onDelete('cascade');
            $table->foreign('merchant_transaction_id')->references('id')->on('merchant_transactions')->onDelete('set null');
            $table->foreign('statement_upload_id')->references('id')->on('merchant_statement_uploads')->onDelete('set null');
            $table->index(['merchant_account_id', 'internal_status']);
            $table->index('due_at');
            $table->index('reason_code');
        });

        // ═══════════════════════════════════════════════════════
        // 9. MERCHANT FINANCIAL ENTRIES
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_account_id');
            $table->unsignedBigInteger('statement_upload_id')->nullable();
            $table->string('entry_type'); // fee, reserve_hold, reserve_release, payout, deposit, adjustment
            $table->string('category')->nullable(); // processing_fee, chargeback_fee, monthly_fee, etc.
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->nullable()->default('USD');
            $table->date('entry_date')->nullable();
            $table->string('external_reference')->nullable();
            $table->json('raw_data_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_account_id')->references('id')->on('merchant_accounts')->onDelete('cascade');
            $table->foreign('statement_upload_id')->references('id')->on('merchant_statement_uploads')->onDelete('set null');
            $table->index(['merchant_account_id', 'entry_type']);
            $table->index('entry_date');
        });

        // ═══════════════════════════════════════════════════════
        // 10. TRANSACTION EVENTS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_transaction_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_transaction_id');
            $table->string('event_type');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_transaction_id')->references('id')->on('merchant_transactions')->onDelete('cascade');
            $table->index('event_type');
        });

        // ═══════════════════════════════════════════════════════
        // 11. CHARGEBACK EVENTS
        // ═══════════════════════════════════════════════════════
        Schema::create('merchant_chargeback_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_chargeback_id');
            $table->string('event_type');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->foreign('merchant_chargeback_id')->references('id')->on('merchant_chargebacks')->onDelete('cascade');
            $table->index('event_type');
        });

        // ═══════════════════════════════════════════════════════
        // 12. FINANCE SETTINGS
        // ═══════════════════════════════════════════════════════
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_chargeback_events');
        Schema::dropIfExists('merchant_transaction_events');
        Schema::dropIfExists('merchant_financial_entries');
        Schema::dropIfExists('merchant_chargebacks');
        Schema::dropIfExists('merchant_transactions');
        Schema::dropIfExists('merchant_import_failures');
        Schema::dropIfExists('merchant_import_batches');
        Schema::dropIfExists('merchant_statement_line_items');
        Schema::dropIfExists('merchant_statement_summaries');
        Schema::dropIfExists('merchant_statement_uploads');
        Schema::dropIfExists('merchant_accounts');
        Schema::dropIfExists('finance_settings');
    }
};
