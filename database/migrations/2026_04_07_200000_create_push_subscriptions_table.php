<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('endpoint');
            $table->string('p256dh_key', 500);
            $table->string('auth_token', 500);
            $table->string('content_encoding', 20)->default('aesgcm');
            $table->string('user_agent', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
