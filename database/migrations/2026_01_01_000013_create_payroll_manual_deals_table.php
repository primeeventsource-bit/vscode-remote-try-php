<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_manual_deals')) {
            return;
        }

        Schema::create('payroll_manual_deals', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->date('week_start');
            $table->string('customer_name', 255);
            $table->decimal('amount', 12, 2);
            $table->string('deal_date', 50)->nullable();
            $table->string('was_vd', 3)->default('No');
            $table->string('created_by', 50);
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_manual_deals');
    }
};
