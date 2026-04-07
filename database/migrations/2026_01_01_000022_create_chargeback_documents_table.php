<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('chargeback_documents')) {
            return;
        }

        Schema::create('chargeback_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chargeback_id')->constrained('chargebacks');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('chargeback_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargeback_documents');
    }
};
