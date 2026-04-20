<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('parser_logs')) return;

        Schema::create('parser_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('merchant_statements')->cascadeOnDelete();
            $table->string('level', 12)->default('info'); // info, warning, error
            $table->string('section', 32)->nullable();
            $table->unsignedSmallInteger('page_number')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['statement_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_logs');
    }
};
