<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('merchant_accounts', function (Blueprint $table): void {
            if (!Schema::hasColumn('merchant_accounts', 'merchant_number')) {
                $table->string('merchant_number', 64)->nullable()->after('mid_masked');
            }
            if (!Schema::hasColumn('merchant_accounts', 'association_number')) {
                $table->string('association_number', 64)->nullable()->after('merchant_number');
            }
            if (!Schema::hasColumn('merchant_accounts', 'routing_last4')) {
                $table->string('routing_last4', 4)->nullable()->after('association_number');
            }
            if (!Schema::hasColumn('merchant_accounts', 'deposit_account_last4')) {
                $table->string('deposit_account_last4', 4)->nullable()->after('routing_last4');
            }
            if (!Schema::hasColumn('merchant_accounts', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('deposit_account_last4');
            }
            if (!Schema::hasColumn('merchant_accounts', 'profit_methodology')) {
                $table->string('profit_methodology', 16)->default('net_deposit')->after('currency');
            }
            if (!Schema::hasColumn('merchant_accounts', 'notes')) {
                $table->text('notes')->nullable()->after('profit_methodology');
            }
        });

        // Add parser_slug to processors for strategy pattern routing
        Schema::table('processors', function (Blueprint $table): void {
            if (!Schema::hasColumn('processors', 'parser_slug')) {
                $table->string('parser_slug', 32)->nullable()->after('provider_type');
            }
            if (!Schema::hasColumn('processors', 'detection_patterns')) {
                $table->json('detection_patterns')->nullable()->after('parser_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchant_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'merchant_number', 'association_number', 'routing_last4',
                'deposit_account_last4', 'currency', 'profit_methodology', 'notes',
            ]);
        });
        Schema::table('processors', function (Blueprint $table): void {
            $table->dropColumn(['parser_slug', 'detection_patterns']);
        });
    }
};
