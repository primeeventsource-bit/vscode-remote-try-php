<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_recent_gifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('gif_external_id', 120);
            $table->string('gif_provider', 30);
            $table->text('gif_url');
            $table->text('gif_preview_url')->nullable();
            $table->string('gif_title')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'gif_external_id', 'gif_provider'], 'user_recent_gifs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recent_gifs');
    }
};
