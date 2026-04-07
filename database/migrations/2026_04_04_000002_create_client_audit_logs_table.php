<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('user_role', 50);
            $table->foreignId('deal_id')->constrained('deals');
            $table->string('action', 50); // viewed, edited, viewed_banking, edited_banking, viewed_payment, edited_payment, viewed_deal_sheet, edited_deal_sheet
            $table->string('section', 50)->nullable(); // client_info, deal_sheet, banking, payment_profile, audit_logs
            $table->json('changed_fields')->nullable(); // array of field names that changed
            $table->json('before_values')->nullable(); // previous values (sensitive fields masked)
            $table->json('after_values')->nullable(); // new values (sensitive fields masked)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['deal_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_audit_logs');
    }
};
