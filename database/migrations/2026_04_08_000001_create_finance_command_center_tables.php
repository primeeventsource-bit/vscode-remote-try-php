<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // 1. MERCHANT ACCOUNTS — alter existing or create new
        // ═══════════════════════════════════════════════════════
        if (Schema::hasTable('merchant_accounts')) {
            // Table exists from old migration — add missing columns
            Schema::table('merchant_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('merchant_accounts', 'account_name')) {
                    $table->string('account_name')->default('')->after('id');
                }
                if (!Schema::hasColumn('merchant_accounts', 'mid_number')) {
                    $table->string('mid_number')->default('')->after('account_name');
                }
                if (!Schema::hasColumn('merchant_accounts', 'processor_name')) {
                    $table->string('processor_name')->default('Unknown')->after('mid_number');
                }
                if (!Schema::hasColumn('merchant_accounts', 'gateway_name')) {
                    $table->string('gateway_name')->nullable()->after('processor_name');
                }
                if (!Schema::hasColumn('merchant_accounts', 'descriptor')) {
                    $table->string('descriptor')->nullable()->after('gateway_name');
                }
                if (!Schema::hasColumn('merchant_accounts', 'business_name')) {
                    $table->string('business_name')->nullable()->after('descriptor');
                }
                if (!Schema::hasColumn('merchant_accounts', 'account_status')) {
                    $table->string('account_status')->default('active')->after('business_name');
                }
                if (!Schema::hasColumn('merchant_accounts', 'currency')) {
                    $table->string('currency', 10)->nullable()->default('USD')->after('account_status');
                }
                if (!Schema::hasColumn('merchant_accounts', 'timezone')) {
                    $table->string('timezone')->nullable()->default('America/New_York')->after('currency');
                }
                if (!Schema::hasColumn('merchant_accounts', 'notes')) {
                    $table->text('notes')->nullable()->after('timezone');
                }
                if (!Schema::hasColumn('merchant_accounts', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('notes');
                }
                if (!Schema::hasColumn('merchant_accounts', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('merchant_accounts', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
            });

            // Backfill from old columns if they exist
            if (Schema::hasColumn('merchant_accounts', 'name') && Schema::hasColumn('merchant_accounts', 'account_name')) {
                \Illuminate\Support\Facades\DB::statement("UPDATE merchant_accounts SET account_name = COALESCE(name, '') WHERE account_name = '' OR account_name IS NULL");
            }
            if (Schema::hasColumn('merchant_accounts', 'mid_masked') && Schema::hasColumn('merchant_accounts', 'mid_number')) {
                \Illuminate\Support\Facades\DB::statement("UPDATE merchant_accounts SET mid_number = COALESCE(mid_masked, CONCAT('MID-', id)) WHERE mid_number = '' OR mid_number IS NULL");
            }
            if (Schema::hasColumn('merchant_accounts', 'active') && Schema::hasColumn('merchant_accounts', 'is_active')) {
                \Illuminate\Support\Facades\DB::statement("UPDATE merchant_accounts SET is_active = active WHERE is_active = 1");
            }
        } else {
            Schema::create('merchant_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('account_name');
                $table->string('mid_number');
                $table->string('processor_name');
                $table->string('gateway_name')->nullable();
                $table->string('descriptor')->nullable();
                $table->string('business_name')->nullable();
                $table->string('account_status')->default('active');
                $table->string('currency', 10)->nullable()->default('USD');
                $table->string('timezone')->nullable()->default('America/New_York');
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════
        // 2–12: Create all other tables only if they don't exist
        // ═══════════════════════════════════════════════════════

        if (!Schema::hasTable('merchant_statement_uploads')) {
            Schema::create('merchant_statement_uploads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_account_id')->nullable();
                $table->string('original_filename');
                $table->string('file_path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('detected_processor')->nullable();
                $table->string('detected_statement_type')->nullable();
                $table->string('processing_status')->default('pending');
                $table->decimal('confidence_score', 5, 2)->nullable();
                $table->unsignedBigInteger('uploaded_by');
                $table->timestamp('uploaded_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index('processing_status');
                $table->index('uploaded_at');
            });
        }

        if (!Schema::hasTable('merchant_statement_summaries')) {
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
                $table->index(['merchant_account_id', 'statement_start_date']);
            });
        }

        if (!Schema::hasTable('merchant_statement_line_items')) {
            Schema::create('merchant_statement_line_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_statement_upload_id');
                $table->unsignedBigInteger('merchant_account_id')->nullable();
                $table->string('line_type');
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
                $table->index(['line_type', 'needs_review']);
                $table->index('transaction_date');
            });
        }

        if (!Schema::hasTable('merchant_import_batches')) {
            Schema::create('merchant_import_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_statement_upload_id')->nullable();
                $table->unsignedBigInteger('merchant_account_id')->nullable();
                $table->string('import_type');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->unsignedInteger('duplicate_rows')->default(0);
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                $table->index('status');
            });
        }

        if (!Schema::hasTable('merchant_import_failures')) {
            Schema::create('merchant_import_failures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_import_batch_id');
                $table->unsignedInteger('row_number')->nullable();
                $table->string('error_type');
                $table->text('error_message');
                $table->json('row_data_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('merchant_transactions')) {
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
                $table->string('transaction_status')->default('approved');
                $table->string('payment_status')->nullable();
                $table->string('refund_status')->nullable();
                $table->string('transaction_type')->nullable();
                $table->date('transaction_date');
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_batch_id')->nullable();
                $table->json('raw_data_json')->nullable();
                $table->timestamps();
                $table->index(['merchant_account_id', 'transaction_date']);
                $table->index('transaction_status');
                $table->index('external_transaction_id');
            });
        }

        if (!Schema::hasTable('merchant_chargebacks')) {
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
                $table->string('internal_status')->default('new');
                $table->string('evidence_status')->nullable();
                $table->date('opened_at')->nullable();
                $table->date('due_at')->nullable();
                $table->date('resolved_at')->nullable();
                $table->string('outcome')->nullable();
                $table->text('notes')->nullable();
                $table->json('raw_data_json')->nullable();
                $table->timestamps();
                $table->index(['merchant_account_id', 'internal_status']);
                $table->index('due_at');
                $table->index('reason_code');
            });
        }

        if (!Schema::hasTable('merchant_financial_entries')) {
            Schema::create('merchant_financial_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_account_id');
                $table->unsignedBigInteger('statement_upload_id')->nullable();
                $table->string('entry_type');
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->nullable()->default('USD');
                $table->date('entry_date')->nullable();
                $table->string('external_reference')->nullable();
                $table->json('raw_data_json')->nullable();
                $table->timestamps();
                $table->index(['merchant_account_id', 'entry_type']);
                $table->index('entry_date');
            });
        }

        if (!Schema::hasTable('merchant_transaction_events')) {
            Schema::create('merchant_transaction_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_transaction_id');
                $table->string('event_type');
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();
                $table->index('event_type');
            });
        }

        if (!Schema::hasTable('merchant_chargeback_events')) {
            Schema::create('merchant_chargeback_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_chargeback_id');
                $table->string('event_type');
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();
                $table->index('event_type');
            });
        }

        if (!Schema::hasTable('finance_settings')) {
            Schema::create('finance_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
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
        Schema::dropIfExists('finance_settings');
    }
};
