<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('customer_created_sms_enabled')->default(true)->after('payment_status_sms_enabled');
            $table->boolean('partner_created_sms_enabled')->default(true)->after('customer_created_sms_enabled');
            $table->string('customer_created_message_template', 500)
                ->default('Hi {{name}}, your account is ready. Login with mobile {{mobile}}.')
                ->after('payment_status_templates');
            $table->string('partner_created_message_template', 500)
                ->default('Hi {{name}}, you were added as a partner at {{shop}}. Login with mobile {{mobile}}.')
                ->after('customer_created_message_template');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'customer_created_sms_enabled',
                'partner_created_sms_enabled',
                'customer_created_message_template',
                'partner_created_message_template',
            ]);
        });
    }
};
