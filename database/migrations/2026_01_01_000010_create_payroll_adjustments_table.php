<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_adjustments')) {
            return;
        }

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_id');
            $table->string('user_id', 50);
            $table->string('type', 20);
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->string('created_by', 50);
            $table->dateTime('created_at')->nullable();

            $table->foreign('entry_id')->references('id')->on('payroll_entries')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
