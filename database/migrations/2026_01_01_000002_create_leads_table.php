<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('resort')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('phone1', 50)->nullable();
            $table->string('phone2', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('st', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('resort_location')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('original_fronter')->nullable()->constrained('users')->onDelete('set null');
            $table->string('disposition', 100)->nullable();
            $table->string('transferred_to', 100)->nullable();
            $table->string('source', 20)->default('manual');
            $table->dateTime('callback_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
