<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('merchant_accounts')) {
            return;
        }

        Schema::create('merchant_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('processor_id')->nullable()->constrained('processors')->nullOnDelete();
            $table->string('name');
            $table->string('mid_masked', 32)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['processor_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_accounts');
    }
};
