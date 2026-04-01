<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('processors')) {
            return;
        }

        Schema::create('processors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider_type')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processors');
    }
};
