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
        'customer_created_sms_enabled',
        'partner_created_sms_enabled',
        'offline_bill_sms_enabled',
        'reward_points_sms_enabled',
        'endpoint',
        'http_method',
        'payload_params',
        'message_template',
        'order_message_template',
        'order_status_templates',
        'payment_status_templates',
        'customer_created_message_template',
        'partner_created_message_template',
        'offline_bill_debit_message_template',
        'offline_bill_credit_message_template',
        'offline_bill_partner_reward_message_template',
        'offline_bill_dues_reminder_message_template',
        'reward_points_granted_message_template',
        'reward_redemption_requested_message_template',
        'reward_redemption_requested_owner_message_template',
        'reward_redemption_approved_message_template',
        'reward_redemption_rejected_message_template',
        'otp_ttl_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'order_sms_enabled' => 'boolean',
            'order_status_sms_enabled' => 'boolean',
            'payment_status_sms_enabled' => 'boolean',
            'customer_created_sms_enabled' => 'boolean',
            'partner_created_sms_enabled' => 'boolean',
            'offline_bill_sms_enabled' => 'boolean',
            'reward_points_sms_enabled' => 'boolean',
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

    public static function defaultCustomerCreatedTemplate(): string
    {
        return 'Hi {{name}}, your account is ready. Login with mobile {{mobile}}.';
    }

    public static function defaultPartnerCreatedTemplate(): string
    {
        return 'Hi {{name}}, you were added as a partner at {{shop}}. Login with mobile {{mobile}}.';
    }

    public static function defaultOfflineBillDebitTemplate(): string
    {
        return 'Hi {{customer}}, a debit of Rs {{amount}} was added at {{shop}}. Closing balance Rs {{balance}}.';
    }

    public static function defaultOfflineBillCreditTemplate(): string
    {
        return 'Hi {{customer}}, a credit of Rs {{amount}} was recorded at {{shop}} via {{payment_mode}}. Closing balance Rs {{balance}}.';
    }

    public static function defaultOfflineBillPartnerRewardTemplate(): string
    {
        return 'Hi {{partner}}, you earned {{points}} reward points at {{shop}} for {{customer}}. Amount Rs {{amount}}.';
    }

    public static function defaultOfflineBillDuesReminderTemplate(): string
    {
        return 'Hi {{customer}}, a payment of Rs {{balance}} is pending at {{shop}}. Please pay at the earliest. Thank you.';
    }

    public static function defaultRewardPointsGrantedTemplate(): string
    {
        return 'Hi {{partner}}, {{points}} reward points were added at {{shop}}. Your balance is {{balance}}.';
    }

    public static function defaultRewardRedemptionRequestedTemplate(): string
    {
        return 'Hi {{partner}}, your request to redeem {{points}} points ({{type}}) at {{shop}} has been submitted. Waiting for shop owner approval.';
    }

    public static function defaultRewardRedemptionRequestedOwnerTemplate(): string
    {
        return '{{partner}} requested to redeem {{points}} points ({{type}}) at {{shop}}.';
    }

    public static function defaultRewardRedemptionApprovedTemplate(): string
    {
        return 'Hi {{partner}}, your request to redeem {{points}} points at {{shop}} was approved. Your balance is {{balance}}.';
    }

    public static function defaultRewardRedemptionRejectedTemplate(): string
    {
        return 'Hi {{partner}}, your request to redeem {{points}} points at {{shop}} was rejected. Reason: {{reason}}. Your balance is {{balance}}.';
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
            'customer_created_sms_enabled' => true,
            'partner_created_sms_enabled' => true,
            'offline_bill_sms_enabled' => true,
            'reward_points_sms_enabled' => true,
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
            'customer_created_message_template' => static::defaultCustomerCreatedTemplate(),
            'partner_created_message_template' => static::defaultPartnerCreatedTemplate(),
            'offline_bill_debit_message_template' => static::defaultOfflineBillDebitTemplate(),
            'offline_bill_credit_message_template' => static::defaultOfflineBillCreditTemplate(),
            'offline_bill_partner_reward_message_template' => static::defaultOfflineBillPartnerRewardTemplate(),
            'offline_bill_dues_reminder_message_template' => static::defaultOfflineBillDuesReminderTemplate(),
            'reward_points_granted_message_template' => static::defaultRewardPointsGrantedTemplate(),
            'reward_redemption_requested_message_template' => static::defaultRewardRedemptionRequestedTemplate(),
            'reward_redemption_requested_owner_message_template' => static::defaultRewardRedemptionRequestedOwnerTemplate(),
            'reward_redemption_approved_message_template' => static::defaultRewardRedemptionApprovedTemplate(),
            'reward_redemption_rejected_message_template' => static::defaultRewardRedemptionRejectedTemplate(),
            'otp_ttl_minutes' => 10,
        ]);
    }
}
