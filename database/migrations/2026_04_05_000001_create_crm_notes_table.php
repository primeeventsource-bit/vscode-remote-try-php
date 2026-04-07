<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->string('noteable_type', 50); // App\Models\Deal or deals/clients
            $table->unsignedBigInteger('noteable_id');
            $table->longText('body');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');
            $table->timestamp('sent_to_chat_at')->nullable();
            $table->foreignId('sent_to_chat_by_user_id')->nullable()->constrained('users');
            $table->boolean('internal_only')->default(true);
            $table->timestamps();

            $table->index(['noteable_type', 'noteable_id']);
            $table->index('created_by_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
    }
};
