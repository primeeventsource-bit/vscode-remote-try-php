<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deal_closers')) {
            Schema::create('deal_closers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('comm_pct', 5, 2)->default(10);
                $table->decimal('comm_amount', 10, 2)->default(0);
                $table->decimal('snr_deduction', 10, 2)->default(0);
                $table->decimal('vd_deduction', 10, 2)->default(0);
                $table->decimal('net_pay', 10, 2)->default(0);
                $table->boolean('is_original')->default(false);
                $table->timestamps();
                $table->unique(['deal_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_closers');
    }
};
