<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full Twilio communications schema — 7 tables for production SMS/MMS/voice.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Contact Phone Numbers ─────────────────────────
        if (! Schema::hasTable('contact_phone_numbers')) {
            Schema::create('contact_phone_numbers', function (Blueprint $table) {
                $table->id();
                $table->string('phoneable_type', 100)->index();
                $table->unsignedBigInteger('phoneable_id');
                $table->string('label', 30)->default('mobile'); // mobile, home, office, whatsapp
                $table->string('raw_phone', 50);
                $table->string('normalized_phone', 20)->nullable()->index(); // E.164
                $table->string('national_phone', 30)->nullable();
                $table->string('country_code', 5)->default('US');
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_sms_capable')->default(true);
                $table->boolean('is_voice_capable')->default(true);
                $table->string('validation_status', 20)->default('pending'); // pending, valid, invalid
                $table->timestamp('last_validated_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['phoneable_type', 'phoneable_id']);
                $table->index(['normalized_phone', 'phoneable_type']);
            });
        }

        // ── 2. Communication Threads ─────────────────────────
        if (! Schema::hasTable('communication_threads')) {
            Schema::create('communication_threads', function (Blueprint $table) {
                $table->id();
                $table->string('subject', 255)->nullable();
                $table->string('threadable_type', 100)->index();
                $table->unsignedBigInteger('threadable_id');
                $table->string('channel', 20)->default('sms'); // sms, mms, voice, whatsapp
                $table->foreignId('assigned_user_id')->nullable()->constrained('users');
                $table->string('phone_number', 20)->nullable(); // the external number
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('last_inbound_at')->nullable();
                $table->timestamp('last_outbound_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->string('status', 20)->default('open'); // open, closed, archived
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['threadable_type', 'threadable_id']);
                $table->index(['phone_number', 'channel']);
                $table->index('last_message_at');
            });
        }

        // ── 3. Communications (messages + call events) ───────
        if (! Schema::hasTable('communications')) {
            Schema::create('communications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->nullable()->constrained('communication_threads');
                $table->string('communicable_type', 100)->nullable();
                $table->unsignedBigInteger('communicable_id')->nullable();
                $table->foreignId('contact_phone_number_id')->nullable()->constrained('contact_phone_numbers');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('provider', 30)->default('twilio');
                $table->string('provider_message_sid', 50)->nullable()->unique();
                $table->string('provider_call_sid', 50)->nullable();
                $table->string('channel', 20)->default('sms');
                $table->string('direction', 20); // inbound, outbound, system
                $table->string('message_type', 30)->default('text'); // text, media, status, call_event, opt_out, opt_in
                $table->string('to_phone', 20);
                $table->string('from_phone', 20);
                $table->longText('body')->nullable();
                $table->unsignedSmallInteger('media_count')->default(0);
                $table->json('media_urls')->nullable();
                $table->string('status', 30)->default('queued'); // queued, sending, sent, delivered, undelivered, failed, received, read
                $table->string('error_code', 20)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['communicable_type', 'communicable_id']);
                $table->index(['to_phone', 'created_at']);
                $table->index(['from_phone', 'created_at']);
                $table->index(['status', 'channel']);
                $table->index('thread_id');
            });
        }

        // ── 4. Communication Events (status callbacks) ───────
        if (! Schema::hasTable('communication_events')) {
            Schema::create('communication_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('communication_id')->nullable()->constrained('communications');
                $table->string('provider', 30)->default('twilio');
                $table->string('event_type', 50);
                $table->string('provider_sid', 50)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->string('processing_status', 20)->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['provider_sid', 'event_type']);
            });
        }

        // ── 5. Twilio Webhook Logs ───────────────────────────
        if (! Schema::hasTable('twilio_webhook_logs')) {
            Schema::create('twilio_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 100)->nullable()->unique(); // dedup
                $table->string('endpoint', 255);
                $table->string('request_method', 10)->default('POST');
                $table->boolean('signature_valid')->default(false);
                $table->json('payload')->nullable();
                $table->boolean('processed')->default(false);
                $table->timestamp('processed_at')->nullable();
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('error_message')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index('processed');
                $table->index('created_at');
            });
        }

        // ── 6. Contact Consent Logs ──────────────────────────
        if (! Schema::hasTable('contact_consent_logs')) {
            Schema::create('contact_consent_logs', function (Blueprint $table) {
                $table->id();
                $table->string('contactable_type', 100)->nullable();
                $table->unsignedBigInteger('contactable_id')->nullable();
                $table->string('phone_number', 50);
                $table->string('normalized_phone', 20)->nullable()->index();
                $table->string('consent_status', 20)->default('unknown'); // opted_in, opted_out, pending, unknown
                $table->string('source', 30)->default('manual_admin'); // web_form, manual_admin, inbound_keyword, import, api
                $table->text('source_details')->nullable();
                $table->foreignId('captured_by_user_id')->nullable()->constrained('users');
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['contactable_type', 'contactable_id']);
            });
        }

        // ── 7. Message Templates ─────────────────────────────
        if (! Schema::hasTable('message_templates')) {
            Schema::create('message_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 100)->unique();
                $table->longText('body');
                $table->string('channel', 20)->default('sms');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('contact_consent_logs');
        Schema::dropIfExists('twilio_webhook_logs');
        Schema::dropIfExists('communication_events');
        Schema::dropIfExists('communications');
        Schema::dropIfExists('communication_threads');
        Schema::dropIfExists('contact_phone_numbers');
    }
};
