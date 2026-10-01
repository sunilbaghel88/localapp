<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('offline_bill_sms_enabled')->default(true)->after('partner_created_sms_enabled');
            $table->string('offline_bill_debit_message_template', 500)
                ->default('Hi {{customer}}, a debit of Rs {{amount}} was added at {{shop}}. Closing balance Rs {{balance}}.')
                ->after('partner_created_message_template');
            $table->string('offline_bill_credit_message_template', 500)
                ->default('Hi {{customer}}, a credit of Rs {{amount}} was recorded at {{shop}} via {{payment_mode}}. Closing balance Rs {{balance}}.')
                ->after('offline_bill_debit_message_template');
            $table->string('offline_bill_partner_reward_message_template', 500)
                ->default('Hi {{partner}}, you earned {{points}} reward points at {{shop}} for {{customer}}. Amount Rs {{amount}}.')
                ->after('offline_bill_credit_message_template');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'offline_bill_sms_enabled',
                'offline_bill_debit_message_template',
                'offline_bill_credit_message_template',
                'offline_bill_partner_reward_message_template',
            ]);
        });
    }
};
