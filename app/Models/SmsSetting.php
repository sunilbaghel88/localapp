<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'order_sms_enabled',
        'endpoint',
        'http_method',
        'payload_params',
        'message_template',
        'order_message_template',
        'otp_ttl_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'order_sms_enabled' => 'boolean',
            'payload_params' => 'array',
            'otp_ttl_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting) {
            return $setting;
        }

        return static::query()->create([
                'is_enabled' => false,
            'order_sms_enabled' => true,
            'endpoint' => 'http://sms.endmile.in/WebServiceSMS.aspx',
            'http_method' => 'GET',
            'payload_params' => [
                'User' => '',
                'passwd' => '',
                'mobilenumber' => '{{mobile}}',
                'message' => '{{message}}',
                'sid' => '',
                'mtype' => 'N',
            ],
            'message_template' => 'Your OTP is {{otp}}.',
            'order_message_template' => 'Your order #{{order_id}} at {{shop}} is placed. Amount Rs {{total}}. Thank you.',
            'otp_ttl_minutes' => 10,
        ]);
    }
}
