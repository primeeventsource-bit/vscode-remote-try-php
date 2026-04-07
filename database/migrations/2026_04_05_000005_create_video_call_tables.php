<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_rooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->string('type', 20)->default('group');
            $table->string('status', 20)->default('waiting'); // waiting, active, ended
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
        });

        Schema::create('video_room_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('video_rooms');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->string('role', 20)->default('participant'); // host, participant
            $table->string('invite_status', 20)->default('pending'); // pending, accepted, declined, missed
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('mic_enabled')->default(true);
            $table->boolean('camera_enabled')->default(true);
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['user_id', 'invite_status']);
        });

        Schema::create('video_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('video_rooms');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('event_type', 30);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'event_type']);
        });

        // Signaling table for WebRTC offer/answer/ice without WebSocket
        Schema::create('video_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('video_rooms');
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->string('type', 20); // offer, answer, ice
            $table->longText('payload');
            $table->boolean('consumed')->default(false);
            $table->timestamps();

            $table->index(['to_user_id', 'room_id', 'consumed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_signals');
        Schema::dropIfExists('video_call_logs');
        Schema::dropIfExists('video_room_participants');
        Schema::dropIfExists('video_rooms');
    }
};
