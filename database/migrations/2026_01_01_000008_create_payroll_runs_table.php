<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->string('status', 20)->default('draft');
            $table->string('created_by', 50);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->text('notes')->nullable();

            $table->unique('week_start', 'uq_runs_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
