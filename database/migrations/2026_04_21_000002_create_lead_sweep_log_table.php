<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_sweep_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('field_name', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('rule', 64)->index();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'field_name']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sweep_log');
    }
};
