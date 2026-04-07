<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified meetings system for both direct calls and group meetings.
 * One table handles DM video calls, group meetings, scheduled meetings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            Schema::create('meetings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('type', 30)->default('direct');       // direct, group, scheduled, instant
                $table->string('source_type', 30)->nullable();       // bubble_dm, group_chat, meetings_module
                $table->unsignedBigInteger('source_id')->nullable(); // chat_id or null
                $table->string('provider', 30)->default('twilio');
                $table->string('provider_room_name', 255)->unique();
                $table->string('provider_room_sid', 100)->nullable();
                $table->string('title', 255)->nullable();
                $table->foreignId('host_user_id')->constrained('users');
                $table->string('status', 30)->default('pending');    // pending, ringing, live, ended, declined, missed, failed
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedSmallInteger('max_participants')->default(20);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('host_user_id');
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('meeting_participants')) {
            Schema::create('meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings');
                $table->foreignId('user_id')->constrained('users');
                $table->string('role', 20)->default('participant');        // host, participant
                $table->string('invite_status', 20)->default('pending');   // pending, accepted, declined, missed
                $table->string('attendance_status', 20)->default('not_joined'); // not_joined, joined, left
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->boolean('audio_enabled')->default(true);
                $table->boolean('video_enabled')->default(true);
                $table->boolean('screen_sharing')->default(false);
                $table->string('participant_identity', 100)->nullable();
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id']);
                $table->index(['user_id', 'invite_status']);
            });
        }

        if (! Schema::hasTable('meeting_events')) {
            Schema::create('meeting_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('event_type', 50);
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['meeting_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_events');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
