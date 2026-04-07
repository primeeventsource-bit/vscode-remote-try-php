<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_folders')) {
            Schema::create('crm_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('module_type', 30); // documents, sheets
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_documents')) {
            Schema::create('crm_documents', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->longText('content')->nullable();
                $table->string('type', 20)->default('rich_text'); // rich_text, uploaded
                $table->foreignId('owner_id')->constrained('users');
                $table->foreignId('folder_id')->nullable()->constrained('crm_folders');
                $table->string('status', 20)->default('active'); // active, archived, deleted
                $table->boolean('is_uploaded')->default(false);
                $table->string('original_filename')->nullable();
                $table->string('stored_path')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
            });
        }

        if (!Schema::hasTable('crm_sheets')) {
            Schema::create('crm_sheets', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->json('data')->nullable(); // rows/cols JSON
                $table->foreignId('owner_id')->constrained('users');
                $table->foreignId('folder_id')->nullable()->constrained('crm_folders');
                $table->string('status', 20)->default('active');
                $table->boolean('is_uploaded')->default(false);
                $table->string('original_filename')->nullable();
                $table->string('stored_path')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
            });
        }

        if (!Schema::hasTable('crm_document_permissions')) {
            Schema::create('crm_document_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('crm_documents');
                $table->foreignId('user_id')->constrained('users');
                $table->string('permission_type', 10)->default('view'); // view, edit
                $table->foreignId('granted_by')->nullable()->constrained('users');
                $table->timestamp('created_at')->nullable();
                $table->unique(['document_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('crm_sheet_permissions')) {
            Schema::create('crm_sheet_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sheet_id')->constrained('crm_sheets');
                $table->foreignId('user_id')->constrained('users');
                $table->string('permission_type', 10)->default('view');
                $table->foreignId('granted_by')->nullable()->constrained('users');
                $table->timestamp('created_at')->nullable();
                $table->unique(['sheet_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('crm_file_activity_logs')) {
            Schema::create('crm_file_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('module_type', 30); // documents, sheets
                $table->unsignedBigInteger('record_id');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('action', 50);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['module_type', 'record_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_file_activity_logs');
        Schema::dropIfExists('crm_sheet_permissions');
        Schema::dropIfExists('crm_document_permissions');
        Schema::dropIfExists('crm_sheets');
        Schema::dropIfExists('crm_documents');
        Schema::dropIfExists('crm_folders');
    }
};
