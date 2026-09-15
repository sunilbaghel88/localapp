<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('order_status_sms_enabled')->default(true)->after('order_sms_enabled');
            $table->boolean('payment_status_sms_enabled')->default(true)->after('order_status_sms_enabled');
            $table->json('order_status_templates')->nullable()->after('order_message_template');
            $table->json('payment_status_templates')->nullable()->after('order_status_templates');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'order_status_sms_enabled',
                'payment_status_sms_enabled',
                'order_status_templates',
                'payment_status_templates',
            ]);
        });
    }
};
