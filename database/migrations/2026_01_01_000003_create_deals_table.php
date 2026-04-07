<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->date('timestamp')->nullable();
            $table->date('charged_date')->nullable();
            $table->string('was_vd', 3)->default('No');
            $table->foreignId('fronter')->nullable()->constrained('users')->onDelete('no action');
            $table->foreignId('closer')->nullable()->constrained('users')->onDelete('no action');
            $table->decimal('fee', 12, 2)->default(0.00);
            $table->string('owner_name')->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('city_state_zip')->nullable();
            $table->string('primary_phone', 50)->nullable();
            $table->string('secondary_phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('weeks', 100)->nullable();
            $table->string('asking_rental')->nullable();
            $table->string('resort_name')->nullable();
            $table->string('resort_city_state')->nullable();
            $table->string('exchange_group')->nullable();
            $table->string('bed_bath', 50)->nullable();
            $table->string('usage', 100)->nullable();
            $table->string('asking_sale_price')->nullable();
            $table->string('name_on_card')->nullable();
            $table->string('card_type', 50)->nullable();
            $table->string('bank')->nullable();
            $table->string('card_number')->nullable();
            $table->string('exp_date', 20)->nullable();
            $table->string('cv2', 10)->nullable();
            $table->string('billing_address')->nullable();
            $table->string('bank2')->nullable();
            $table->string('card_number2')->nullable();
            $table->string('exp_date2', 20)->nullable();
            $table->string('cv2_2', 10)->nullable();
            $table->string('using_timeshare')->nullable();
            $table->string('looking_to_get_out')->nullable();
            $table->string('verification_num')->nullable();
            $table->text('notes')->nullable();
            $table->text('login_info')->nullable();
            $table->json('correspondence')->nullable();
            $table->json('files')->nullable();
            $table->string('snr', 50)->nullable();
            $table->string('login')->nullable();
            $table->string('merchant')->nullable();
            $table->string('app_login')->nullable();
            $table->foreignId('assigned_admin')->nullable()->constrained('users')->onDelete('no action');
            $table->string('status', 50)->default('pending_admin');
            $table->string('charged', 10)->default('no');
            $table->string('charged_back', 10)->default('no');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
