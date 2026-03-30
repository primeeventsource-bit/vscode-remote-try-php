<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_deal_overrides')) {
            return;
        }

        Schema::create('payroll_deal_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->string('deal_id', 50);
            $table->date('week_start');
            $table->string('override_type', 20);
            $table->string('override_value', 255);
            $table->dateTime('created_at')->nullable();

            $table->unique(['user_id', 'deal_id', 'week_start', 'override_type'], 'uq_deal_overrides');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deal_overrides');
    }
};
