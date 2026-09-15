<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    public const ORDER_STATUSES = [
        'pending',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
    ];

    public const PAYMENT_STATUSES = [
        'pending',
        'paid',
        'failed',
        'refunded',
    ];

    protected $fillable = [
        'is_enabled',
        'order_sms_enabled',
        'order_status_sms_enabled',
        'payment_status_sms_enabled',
        'endpoint',
        'http_method',
        'payload_params',
        'message_template',
        'order_message_template',
        'order_status_templates',
        'payment_status_templates',
        'otp_ttl_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'order_sms_enabled' => 'boolean',
            'order_status_sms_enabled' => 'boolean',
            'payment_status_sms_enabled' => 'boolean',
            'payload_params' => 'array',
            'order_status_templates' => 'array',
            'payment_status_templates' => 'array',
            'otp_ttl_minutes' => 'integer',
        ];
    }

    public static function defaultOrderStatusTemplates(): array
    {
        return [
            'pending' => 'Your order #{{order_id}} at {{shop}} is pending. Amount Rs {{total}}.',
            'processing' => 'Your order #{{order_id}} at {{shop}} is now being processed.',
            'shipped' => 'Your order #{{order_id}} at {{shop}} has been shipped.',
            'delivered' => 'Your order #{{order_id}} at {{shop}} has been delivered. Thank you.',
            'cancelled' => 'Your order #{{order_id}} at {{shop}} has been cancelled.',
        ];
    }

    public static function defaultPaymentStatusTemplates(): array
    {
        return [
            'pending' => 'Payment for order #{{order_id}} at {{shop}} is pending. Amount Rs {{total}}.',
            'paid' => 'Payment received for order #{{order_id}} at {{shop}}. Amount Rs {{total}}.',
            'failed' => 'Payment for order #{{order_id}} at {{shop}} failed. Amount Rs {{total}}.',
            'refunded' => 'Payment for order #{{order_id}} at {{shop}} has been refunded. Amount Rs {{total}}.',
        ];
    }

    public function templateForOrderStatus(string $status): ?string
    {
        return $this->templateFromMap(
            array_merge(static::defaultOrderStatusTemplates(), $this->order_status_templates ?? []),
            $status,
        );
    }

    public function templateForPaymentStatus(string $status): ?string
    {
        return $this->templateFromMap(
            array_merge(static::defaultPaymentStatusTemplates(), $this->payment_status_templates ?? []),
            $status,
        );
    }

    /**
     * @param  array<string, mixed>  $templates
     */
    protected function templateFromMap(array $templates, string $status): ?string
    {
        $text = trim((string) ($templates[$status] ?? ''));

        return $text === '' ? null : $text;
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
            'order_status_sms_enabled' => true,
            'payment_status_sms_enabled' => true,
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
            'order_status_templates' => static::defaultOrderStatusTemplates(),
            'payment_status_templates' => static::defaultPaymentStatusTemplates(),
            'otp_ttl_minutes' => 10,
        ]);
    }
}
