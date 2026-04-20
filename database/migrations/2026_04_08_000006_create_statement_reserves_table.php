<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('statement_reserves')) return;

        Schema::create('statement_reserves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->unsignedSmallInteger('reserve_day')->nullable();
            $table->date('reserve_date')->nullable();
            $table->decimal('reserve_amount', 14, 2)->default(0);
            $table->decimal('release_amount', 14, 2)->default(0);
            $table->decimal('running_balance', 14, 2)->default(0);
            $table->text('raw_row_text')->nullable();
            $table->timestamps();

            $table->index('statement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_reserves');
    }
};
