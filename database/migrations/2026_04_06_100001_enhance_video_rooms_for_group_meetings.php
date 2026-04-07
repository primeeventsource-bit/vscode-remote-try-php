<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance video_rooms + video_room_participants for full group meeting support.
 * Adds: room_name, room_type, max_participants, scheduled_for, privacy, provider fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Enhance video_rooms
        if (! Schema::hasColumn('video_rooms', 'room_name')) {
            Schema::table('video_rooms', function (Blueprint $table) {
                $table->string('room_name', 255)->nullable()->after('name');
                $table->string('room_type', 30)->default('instant')->after('type'); // instant, scheduled, department, coaching, training
                $table->string('provider', 30)->default('twilio')->after('room_type');
                $table->string('provider_room_sid', 100)->nullable()->after('provider');
                $table->string('privacy', 20)->default('private')->after('provider_room_sid'); // private, team, all
                $table->unsignedSmallInteger('max_participants')->default(20)->after('privacy');
                $table->timestamp('scheduled_for')->nullable()->after('ended_at');
                $table->json('settings')->nullable()->after('scheduled_for');
            });
        }

        // Enhance video_room_participants
        if (! Schema::hasColumn('video_room_participants', 'screen_sharing')) {
            Schema::table('video_room_participants', function (Blueprint $table) {
                $table->boolean('screen_sharing')->default(false)->after('camera_enabled');
                $table->string('connection_status', 20)->nullable()->after('screen_sharing'); // connecting, connected, reconnecting, disconnected
                $table->timestamp('last_seen_at')->nullable()->after('connection_status');
            });
        }

        // Video room events table
        if (! Schema::hasTable('video_room_events')) {
            Schema::create('video_room_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('video_room_id')->constrained('video_rooms');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('event_type', 50);
                $table->json('event_payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['video_room_id', 'created_at']);
            });
        }

        // Video room invites table
        if (! Schema::hasTable('video_room_invites')) {
            Schema::create('video_room_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('video_room_id')->constrained('video_rooms');
                $table->foreignId('invited_user_id')->constrained('users');
                $table->foreignId('invited_by_user_id')->nullable()->constrained('users');
                $table->string('invite_type', 30)->default('direct'); // direct, department, broadcast
                $table->string('invite_status', 20)->default('pending'); // pending, accepted, declined, expired
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->index(['invited_user_id', 'invite_status']);
                $table->index(['video_room_id', 'invite_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_room_invites');
        Schema::dropIfExists('video_room_events');

        if (Schema::hasColumn('video_rooms', 'room_name')) {
            Schema::table('video_rooms', function (Blueprint $table) {
                $table->dropColumn(['room_name', 'room_type', 'provider', 'provider_room_sid', 'privacy', 'max_participants', 'scheduled_for', 'settings']);
            });
        }

        if (Schema::hasColumn('video_room_participants', 'screen_sharing')) {
            Schema::table('video_room_participants', function (Blueprint $table) {
                $table->dropColumn(['screen_sharing', 'connection_status', 'last_seen_at']);
            });
        }
    }
};
