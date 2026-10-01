<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->string('offline_bill_dues_reminder_message_template', 500)
                ->default('Hi {{customer}}, a payment of Rs {{balance}} is pending at {{shop}}. Please pay at the earliest. Thank you.')
                ->after('offline_bill_partner_reward_message_template');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn('offline_bill_dues_reminder_message_template');
        });
    }
};
