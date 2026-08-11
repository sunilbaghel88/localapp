<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('endpoint')->nullable();
            $table->string('http_method', 10)->default('GET');
            $table->json('payload_params')->nullable();
            $table->string('message_template')->default('Your OTP is {{otp}}.');
            $table->unsignedTinyInteger('otp_ttl_minutes')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
