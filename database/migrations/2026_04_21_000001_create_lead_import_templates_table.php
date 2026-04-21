<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_import_templates', function (Blueprint $table) {
            $table->id();
            // sha1 of normalized header row — looks up a previously confirmed mapping
            $table->string('header_hash', 40)->unique();
            // Original header row, stored for the admin "remembered mappings" view
            $table->json('headers');
            // Normalized-header => lead-field mapping, confirmed by a user
            $table->json('mapping');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedInteger('use_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_import_templates');
    }
};
