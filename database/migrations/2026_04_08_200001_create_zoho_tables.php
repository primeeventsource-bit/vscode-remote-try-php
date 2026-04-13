<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zoho_tokens')) {
            Schema::create('zoho_tokens', function (Blueprint $table) {
                $table->id();
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('grant_type')->default('authorization_code');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('zoho_sync_logs')) {
            Schema::create('zoho_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->string('sync_type'); // contacts, deals, activities, full
                $table->string('status')->default('pending'); // pending, running, completed, failed
                $table->integer('records_synced')->default(0);
                $table->integer('records_created')->default(0);
                $table->integer('records_updated')->default(0);
                $table->integer('records_failed')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('triggered_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('zoho_clients')) {
            Schema::create('zoho_clients', function (Blueprint $table) {
                $table->id();
                $table->string('zoho_id')->unique()->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('account_name')->nullable();
                $table->string('title')->nullable();
                $table->string('department')->nullable();
                $table->text('mailing_address')->nullable();
                $table->string('mailing_city')->nullable();
                $table->string('mailing_state')->nullable();
                $table->string('mailing_zip')->nullable();
                $table->string('mailing_country')->nullable();
                $table->string('lead_source')->nullable();
                $table->string('contact_owner')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('last_synced_at')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamps();

                $table->index('email');
                $table->index('last_name');
                $table->index('account_name');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('zoho_deals')) {
            Schema::create('zoho_deals', function (Blueprint $table) {
                $table->id();
                $table->string('zoho_id')->unique()->nullable();
                $table->unsignedBigInteger('zoho_client_id')->nullable();
                $table->string('deal_name')->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->string('stage')->nullable();
                $table->string('pipeline')->nullable();
                $table->date('closing_date')->nullable();
                $table->string('deal_owner')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->foreign('zoho_client_id')->references('id')->on('zoho_clients')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('zoho_activities')) {
            Schema::create('zoho_activities', function (Blueprint $table) {
                $table->id();
                $table->string('zoho_id')->unique()->nullable();
                $table->unsignedBigInteger('zoho_client_id')->nullable();
                $table->string('activity_type')->nullable(); // call, meeting, task, event
                $table->string('subject')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('activity_date')->nullable();
                $table->string('status')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->foreign('zoho_client_id')->references('id')->on('zoho_clients')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('zoho_notes')) {
            Schema::create('zoho_notes', function (Blueprint $table) {
                $table->id();
                $table->string('zoho_id')->unique()->nullable();
                $table->unsignedBigInteger('zoho_client_id')->nullable();
                $table->text('note_content')->nullable();
                $table->string('note_title')->nullable();
                $table->string('created_by_name')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->foreign('zoho_client_id')->references('id')->on('zoho_clients')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('zoho_client_notes')) {
            Schema::create('zoho_client_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('zoho_client_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('body');
                $table->timestamps();

                $table->foreign('zoho_client_id')->references('id')->on('zoho_clients')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_client_notes');
        Schema::dropIfExists('zoho_notes');
        Schema::dropIfExists('zoho_activities');
        Schema::dropIfExists('zoho_deals');
        Schema::dropIfExists('zoho_sync_logs');
        Schema::dropIfExists('zoho_clients');
        Schema::dropIfExists('zoho_tokens');
    }
};
