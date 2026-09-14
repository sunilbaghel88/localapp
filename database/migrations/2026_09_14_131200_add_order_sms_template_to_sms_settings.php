<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('order_sms_enabled')->default(true)->after('is_enabled');
            $table->string('order_message_template', 500)
                ->default('Your order #{{order_id}} at {{shop}} is placed. Amount Rs {{total}}. Thank you.')
                ->after('message_template');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn(['order_sms_enabled', 'order_message_template']);
        });
    }
};
