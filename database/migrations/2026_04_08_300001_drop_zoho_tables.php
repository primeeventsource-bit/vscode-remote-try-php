<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('zoho_client_notes');
        Schema::dropIfExists('zoho_notes');
        Schema::dropIfExists('zoho_activities');
        Schema::dropIfExists('zoho_deals');
        Schema::dropIfExists('zoho_sync_logs');
        Schema::dropIfExists('zoho_clients');
        Schema::dropIfExists('zoho_tokens');
    }

    public function down(): void
    {
        // Tables are gone permanently
    }
};
