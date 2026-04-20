<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('merchant_statements')) return;

        Schema::create('merchant_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_account_id')->constrained('merchant_accounts')->cascadeOnDelete();
            $table->foreignId('processor_id')->nullable()->constrained('processors')->nullOnDelete();
            $table->string('statement_month', 7); // YYYY-MM
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('credits', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('discount_due', 14, 2)->default(0);
            $table->decimal('discount_paid', 14, 2)->default(0);
            $table->decimal('fees_due', 14, 2)->default(0);
            $table->decimal('fees_paid', 14, 2)->default(0);
            $table->decimal('net_fees_due', 14, 2)->default(0);
            $table->decimal('amount_deducted', 14, 2)->default(0);
            $table->decimal('total_deposits', 14, 2)->default(0);
            $table->decimal('total_chargebacks', 14, 2)->default(0);
            $table->decimal('total_reversals', 14, 2)->default(0);
            $table->decimal('reserve_ending_balance', 14, 2)->default(0);
            $table->string('upload_filename')->nullable();
            $table->string('upload_file_path')->nullable();
            $table->longText('raw_text')->nullable();
            $table->json('parsed_json')->nullable();        // normalized snapshot
            $table->string('detected_processor', 64)->nullable();
            $table->unsignedTinyInteger('detection_confidence')->default(0);
            $table->string('parser_version', 16)->nullable();
            $table->string('ai_parse_status', 24)->default('pending'); // pending|processing|completed|failed|review
            $table->string('validation_status', 16)->default('pending'); // pending|pass|warning|fail
            $table->text('validation_notes')->nullable();
            $table->string('review_status', 16)->default('none'); // none|pending|approved|rejected
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_account_id', 'statement_month'], 'stmt_mid_month_unique');
            $table->index('statement_month');
            $table->index('ai_parse_status');
            $table->index('validation_status');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_statements');
    }
};
