<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargeback_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('deals')->cascadeOnDelete(); // clients ARE deals
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->string('case_number', 100)->index();
            $table->string('card_type', 50)->nullable();
            $table->string('card_brand', 50)->nullable();
            $table->string('processor_name', 100)->nullable();
            $table->string('reason_code', 50)->nullable();
            $table->string('reason_description')->nullable();
            $table->decimal('transaction_amount', 12, 2)->nullable();
            $table->decimal('disputed_amount', 12, 2)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('order_id', 100)->nullable();
            $table->date('response_due_at')->nullable();
            $table->date('sale_date')->nullable();
            $table->date('service_start_date')->nullable();
            $table->string('customer_ip_address', 45)->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->text('internal_comments')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('client_id');
            $table->index('deal_id');
        });

        Schema::create('chargeback_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chargeback_case_id')->constrained('chargeback_cases')->cascadeOnDelete();
            $table->string('document_type', 50)->index();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('status', 20)->default('uploaded'); // missing, uploaded, verified
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('chargeback_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargeback_evidence');
        Schema::dropIfExists('chargeback_cases');
    }
};
