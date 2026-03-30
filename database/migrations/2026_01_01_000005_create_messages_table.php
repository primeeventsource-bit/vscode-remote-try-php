<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('text')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->json('reactions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignId('reply_to')->nullable()->constrained('messages')->onDelete('set null');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
