<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_notes')) {
            return;
        }

        Schema::create('payroll_notes', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->date('week_start');
            $table->text('note');
            $table->string('created_by', 50);
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'week_start'], 'uq_payroll_notes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_notes');
    }
};
