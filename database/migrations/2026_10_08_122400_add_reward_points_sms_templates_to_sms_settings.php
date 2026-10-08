<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('reward_points_sms_enabled')->default(true)->after('offline_bill_sms_enabled');
            $table->string('reward_points_granted_message_template', 500)
                ->default('Hi {{partner}}, {{points}} reward points were added at {{shop}}. Your balance is {{balance}}.')
                ->after('offline_bill_dues_reminder_message_template');
            $table->string('reward_redemption_requested_message_template', 500)
                ->default('Hi {{partner}}, your request to redeem {{points}} points ({{type}}) at {{shop}} has been submitted. Waiting for shop owner approval.')
                ->after('reward_points_granted_message_template');
            $table->string('reward_redemption_requested_owner_message_template', 500)
                ->default('{{partner}} requested to redeem {{points}} points ({{type}}) at {{shop}}.')
                ->after('reward_redemption_requested_message_template');
            $table->string('reward_redemption_approved_message_template', 500)
                ->default('Hi {{partner}}, your request to redeem {{points}} points at {{shop}} was approved. Your balance is {{balance}}.')
                ->after('reward_redemption_requested_owner_message_template');
            $table->string('reward_redemption_rejected_message_template', 500)
                ->default('Hi {{partner}}, your request to redeem {{points}} points at {{shop}} was rejected. Reason: {{reason}}. Your balance is {{balance}}.')
                ->after('reward_redemption_approved_message_template');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'reward_points_sms_enabled',
                'reward_points_granted_message_template',
                'reward_redemption_requested_message_template',
                'reward_redemption_requested_owner_message_template',
                'reward_redemption_approved_message_template',
                'reward_redemption_rejected_message_template',
            ]);
        });
    }
};
