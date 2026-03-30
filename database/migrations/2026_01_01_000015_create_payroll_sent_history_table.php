<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_sent_history')) {
            return;
        }

        Schema::create('payroll_sent_history', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->string('user_name', 100);
            $table->string('user_role', 50);
            $table->date('week_start');
            $table->string('week_label', 100);
            $table->decimal('final_pay', 12, 2);
            $table->string('sent_by', 100);
            $table->dateTime('sent_at')->nullable();
            $table->text('entry_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_sent_history');
    }
};
