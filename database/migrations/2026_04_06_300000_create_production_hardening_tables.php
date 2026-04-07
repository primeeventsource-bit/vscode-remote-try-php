<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production Hardening Migration
 *
 * Creates: chat_participants, call_sessions, call_participants, app_settings, failed_jobs, jobs
 * Adds indexes and columns to existing tables for production performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────
        // 1. chat_participants — normalize JSON members
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('chat_participants')) {
            Schema::create('chat_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('chat_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role_in_chat', 30)->nullable(); // host, admin, member
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->boolean('notifications_muted')->default(false);
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();

                $table->unique(['chat_id', 'user_id']);
                $table->index(['user_id', 'left_at']);
                $table->index('chat_id');

                // Foreign keys — safe with try/catch for existing schema variations
                try {
                    $table->foreign('chat_id')->references('id')->on('chats');
                    $table->foreign('user_id')->references('id')->on('users');
                } catch (\Throwable $e) {
                    // FK may fail on some Azure SQL configurations — indexes still work
                }
            });
        }

        // ──────────────────────────────────────────────
        // 2. call_sessions — unified call tracking
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('call_sessions')) {
            Schema::create('call_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chat_id')->nullable();
                $table->unsignedBigInteger('initiated_by');
                $table->string('type', 20); // audio, video, meeting
                $table->string('provider', 20)->default('twilio');
                $table->string('provider_room_sid', 100)->nullable();
                $table->string('provider_room_name', 100)->nullable();
                $table->string('status', 20)->default('ringing');
                // ringing, accepted, connecting, connected, ended, failed, missed, declined
                $table->timestamp('started_at')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('ended_by')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('initiated_by');
                $table->index('chat_id');

                try {
                    $table->foreign('chat_id')->references('id')->on('chats');
                    $table->foreign('initiated_by')->references('id')->on('users');
                } catch (\Throwable $e) {}
            });
        }

        // ──────────────────────────────────────────────
        // 3. call_participants
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('call_participants')) {
            Schema::create('call_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('call_session_id');
                $table->unsignedBigInteger('user_id');
                $table->string('invite_status', 20)->default('invited');
                // invited, ringing, accepted, declined, missed, joined, left, failed
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();

                $table->unique(['call_session_id', 'user_id']);
                $table->index(['user_id', 'invite_status']);

                try {
                    $table->foreign('call_session_id')->references('id')->on('call_sessions');
                    $table->foreign('user_id')->references('id')->on('users');
                } catch (\Throwable $e) {}
            });
        }

        // ──────────────────────────────────────────────
        // 4. app_settings — replace crm_settings
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('category', 50);
                $table->string('key', 100);
                $table->text('value')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['category', 'key']);
                $table->index('category');
            });
        }

        // ──────────────────────────────────────────────
        // 5. chat_attachments
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('chat_attachments')) {
            Schema::create('chat_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->string('disk', 30)->default('public');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();

                $table->index('message_id');

                try {
                    $table->foreign('message_id')->references('id')->on('messages');
                } catch (\Throwable $e) {}
            });
        }

        // ──────────────────────────────────────────────
        // 6. failed_jobs — Laravel standard
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->text('payload'); // longText causes issues on some SQL Server configs
                $table->text('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // ──────────────────────────────────────────────
        // 7. jobs — Laravel queue table
        // ──────────────────────────────────────────────
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->text('payload'); // longText causes issues on SQL Server
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        // ──────────────────────────────────────────────
        // 8. Backfill chat_participants from chats.members JSON
        // ──────────────────────────────────────────────
        $this->backfillChatParticipants();

        // ──────────────────────────────────────────────
        // 9. Migrate crm_settings → app_settings
        // ──────────────────────────────────────────────
        $this->migrateCrmSettings();

        // ──────────────────────────────────────────────
        // 10. Add missing indexes to existing tables
        // ──────────────────────────────────────────────
        $this->addProductionIndexes();
    }

    /**
     * Backfill chat_participants from the JSON members column on chats table.
     */
    private function backfillChatParticipants(): void
    {
        try {
            if (!Schema::hasTable('chats') || !Schema::hasColumn('chats', 'members')) {
                return;
            }

            $chats = \Illuminate\Support\Facades\DB::table('chats')->get();

            foreach ($chats as $chat) {
                $members = is_string($chat->members)
                    ? json_decode($chat->members, true) ?? []
                    : (is_array($chat->members) ? $chat->members : []);

                foreach ($members as $userId) {
                    $userId = (int) $userId;
                    if ($userId <= 0) continue;

                    $exists = \Illuminate\Support\Facades\DB::table('chat_participants')
                        ->where('chat_id', $chat->id)
                        ->where('user_id', $userId)
                        ->exists();

                    if (!$exists) {
                        \Illuminate\Support\Facades\DB::table('chat_participants')->insert([
                            'chat_id' => $chat->id,
                            'user_id' => $userId,
                            'role_in_chat' => ($chat->created_by ?? null) == $userId ? 'host' : 'member',
                            'joined_at' => $chat->created_at ?? now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Backfill is best-effort — app works without it
            \Illuminate\Support\Facades\Log::warning('chat_participants backfill failed: ' . $e->getMessage());
        }
    }

    /**
     * Copy crm_settings into app_settings with category extraction.
     */
    private function migrateCrmSettings(): void
    {
        try {
            if (!Schema::hasTable('crm_settings') || !Schema::hasTable('app_settings')) {
                return;
            }

            $existing = \Illuminate\Support\Facades\DB::table('crm_settings')->get();

            foreach ($existing as $row) {
                $key = $row->key ?? '';
                $parts = explode('.', $key, 2);
                $category = count($parts) > 1 ? $parts[0] : 'general';
                $settingKey = count($parts) > 1 ? $parts[1] : $key;

                $exists = \Illuminate\Support\Facades\DB::table('app_settings')
                    ->where('category', $category)
                    ->where('key', $settingKey)
                    ->exists();

                if (!$exists) {
                    \Illuminate\Support\Facades\DB::table('app_settings')->insert([
                        'category' => $category,
                        'key' => $settingKey,
                        'value' => $row->value ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('crm_settings migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Add production indexes to existing tables.
     */
    private function addProductionIndexes(): void
    {
        // Add index on messages.chat_id if missing
        try {
            if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'chat_id')) {
                Schema::table('messages', function (Blueprint $table) {
                    $table->index(['chat_id', 'sender_id'], 'msg_chat_sender_idx');
                });
            }
        } catch (\Throwable $e) {
            // Index may already exist
        }

        // Add index on messages.seen_at for unread queries
        try {
            if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'seen_at')) {
                Schema::table('messages', function (Blueprint $table) {
                    $table->index(['chat_id', 'seen_at'], 'msg_chat_seen_idx');
                });
            }
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('call_participants');
        Schema::dropIfExists('call_sessions');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};
