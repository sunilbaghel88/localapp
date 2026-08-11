<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'endpoint',
        'http_method',
        'payload_params',
        'message_template',
        'otp_ttl_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
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
            'otp_ttl_minutes' => 10,
        ]);
    }
}
