<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_transfers')) {
            Schema::create('lead_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('transferred_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('transfer_type', 30)->nullable(); // closer, verification, fronter
                $table->string('transfer_reason')->nullable();
                $table->string('disposition_snapshot')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['lead_id', 'id']);
                $table->index(['to_user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_transfers');
    }
};
