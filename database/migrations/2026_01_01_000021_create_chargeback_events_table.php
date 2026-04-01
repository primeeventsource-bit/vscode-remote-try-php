<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('chargeback_events')) {
            return;
        }

        Schema::create('chargeback_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chargeback_id')->constrained('chargebacks')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['chargeback_id', 'event_date']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargeback_events');
    }
};
