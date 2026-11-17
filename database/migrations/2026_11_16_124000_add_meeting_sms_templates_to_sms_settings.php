<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('meeting_sms_enabled')->default(true)->after('reward_points_sms_enabled');
            $table->string('meeting_created_message_template', 500)
                ->default('Hi {{partner}}, meeting "{{title}}" at {{shop}} on {{date}} at {{time}}. {{details}}')
                ->after('reward_redemption_rejected_message_template');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'meeting_sms_enabled',
                'meeting_created_message_template',
            ]);
        });
    }
};
