<?php

namespace App\Services\Sms;

use App\Models\OfflineBill;
use App\Models\Order;
use App\Models\RewardRedemptionRequest;
use App\Models\Shop;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsSender
{
    public function sendOtp(string $mobile, string $otp): void
    {
        $settings = SmsSetting::current();

        if (! $settings->is_enabled) {
            throw new RuntimeException('SMS sending is disabled. Configure SMS settings in admin.');
        }

        $message = str_replace(
            ['{{otp}}', '{{mobile}}'],
            [$otp, $mobile],
            (string) $settings->message_template
        );

        $this->dispatch($settings, $mobile, $message, [
            '{{otp}}' => $otp,
        ]);
    }

    public function notifyOrderPlaced(Order $order): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $settings->order_sms_enabled) {
                return;
            }

            $order->loadMissing(['user', 'shop', 'address', 'items']);
            $mobile = $this->resolveOrderMobile($order);
            if ($mobile === null) {
                Log::info('Order SMS skipped: no customer mobile', ['order_id' => $order->id]);

                return;
            }

            $placeholders = $this->orderPlaceholders($order, $mobile);
            $message = strtr(
                (string) ($settings->order_message_template ?: 'Your order #{{order_id}} at {{shop}} is placed. Amount Rs {{total}}. Thank you.'),
                $placeholders
            );

            $this->dispatch($settings, $mobile, $message, $placeholders);
        } catch (\Throwable $e) {
            Log::warning('Order SMS failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyCustomerCreated(User $user, ?Shop $shop = null): void
    {
        $this->notifyAccountCreated(
            user: $user,
            shop: $shop,
            enabled: fn (SmsSetting $settings) => (bool) $settings->customer_created_sms_enabled,
            template: fn (SmsSetting $settings) => $settings->customer_created_message_template
                ?: SmsSetting::defaultCustomerCreatedTemplate(),
            kind: 'customer_created',
        );
    }

    public function notifyPartnerCreated(User $user, ?Shop $shop = null): void
    {
        $this->notifyAccountCreated(
            user: $user,
            shop: $shop,
            enabled: fn (SmsSetting $settings) => (bool) $settings->partner_created_sms_enabled,
            template: fn (SmsSetting $settings) => $settings->partner_created_message_template
                ?: SmsSetting::defaultPartnerCreatedTemplate(),
            kind: 'partner_created',
        );
    }

    public function notifyOfflineBillCreated(OfflineBill $bill, float $closingBalance): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $settings->offline_bill_sms_enabled) {
                return;
            }

            $bill->loadMissing(['shop', 'customer', 'partner']);

            $placeholders = $this->offlineBillPlaceholders($bill, $closingBalance, '');

            $customerTemplate = $bill->type === 'credit'
                ? ($settings->offline_bill_credit_message_template ?: SmsSetting::defaultOfflineBillCreditTemplate())
                : ($settings->offline_bill_debit_message_template ?: SmsSetting::defaultOfflineBillDebitTemplate());

            $customerMobile = $this->normalizeMobile($bill->customer?->phone);
            $this->sendTemplated($settings, $customerMobile, $customerTemplate, $placeholders, [
                'kind' => 'offline_bill_customer',
                'bill_id' => $bill->id,
                'user_id' => $bill->customer_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Offline bill SMS failed', [
                'bill_id' => $bill->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function sendOfflineBillDuesReminder(User $customer, Shop $shop, float $balance): void
    {
        $settings = SmsSetting::current();
        if (! $settings->is_enabled) {
            throw new RuntimeException('SMS sending is disabled. Enable it in admin SMS settings.');
        }
        if (! $settings->offline_bill_sms_enabled) {
            throw new RuntimeException('Offline bill SMS is disabled. Enable it in admin SMS settings.');
        }

        $template = trim((string) ($settings->offline_bill_dues_reminder_message_template
            ?: SmsSetting::defaultOfflineBillDuesReminderTemplate()));
        if ($template === '') {
            throw new RuntimeException('Dues reminder SMS template is empty.');
        }

        $mobile = $this->normalizeMobile($customer->phone);
        if ($mobile === null) {
            throw new RuntimeException('Customer has no mobile number.');
        }

        $amount = number_format($balance, 2, '.', '');
        $placeholders = [
            '{{customer}}' => trim((string) ($customer->name ?: 'Customer')),
            '{{shop}}' => $shop->name ?: 'shop',
            '{{balance}}' => $amount,
            '{{amount}}' => $amount,
            '{{mobile}}' => $mobile,
        ];

        $this->dispatch($settings, $mobile, strtr($template, $placeholders), $placeholders);
    }

    /**
     * @param  array<string, string>  $extra
     */
    public function notifyRewardPointsGranted(User $partner, ?Shop $shop, int $points, int $balance, array $extra = []): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $settings->reward_points_sms_enabled) {
                return;
            }

            $template = $settings->reward_points_granted_message_template
                ?: SmsSetting::defaultRewardPointsGrantedTemplate();
            $placeholders = $this->rewardPlaceholders($partner, $shop, $points, $balance, $extra);

            $this->sendTemplated($settings, $this->normalizeMobile($partner->phone), $template, $placeholders, [
                'kind' => 'reward_points_granted',
                'user_id' => $partner->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reward points granted SMS failed', [
                'user_id' => $partner->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyRedemptionRequested(RewardRedemptionRequest $request): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $settings->reward_points_sms_enabled) {
                return;
            }

            $request->loadMissing(['user', 'shop.owner']);
            $partner = $request->user;
            if (! $partner) {
                return;
            }

            $shop = $request->shop;
            $points = (int) $request->requested_points;
            $balance = (int) ($partner->reward_points ?? 0);
            $extra = [
                'type' => (string) $request->redemption_type,
                'status' => 'pending',
                'note' => trim((string) ($request->note ?: '-')),
            ];
            $placeholders = $this->rewardPlaceholders($partner, $shop, $points, $balance, $extra);

            $partnerTemplate = $settings->reward_redemption_requested_message_template
                ?: SmsSetting::defaultRewardRedemptionRequestedTemplate();
            $partnerMobile = $this->normalizeMobile($partner->phone);
            $this->sendTemplated($settings, $partnerMobile, $partnerTemplate, $placeholders, [
                'kind' => 'reward_redemption_requested',
                'user_id' => $partner->id,
                'request_id' => $request->id,
            ]);

            $ownerTemplate = trim((string) ($settings->reward_redemption_requested_owner_message_template
                ?: SmsSetting::defaultRewardRedemptionRequestedOwnerTemplate()));
            if ($ownerTemplate === '') {
                return;
            }

            $ownerMobile = $this->firstValidMobile([
                $shop?->owner?->phone,
                $shop?->phone,
            ]);
            if ($ownerMobile === null || $ownerMobile === $partnerMobile) {
                return;
            }

            $this->sendTemplated($settings, $ownerMobile, $ownerTemplate, $placeholders, [
                'kind' => 'reward_redemption_requested_owner',
                'user_id' => $shop?->user_id,
                'request_id' => $request->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reward redemption request SMS failed', [
                'request_id' => $request->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyRedemptionDecision(RewardRedemptionRequest $request): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $settings->reward_points_sms_enabled) {
                return;
            }

            $request->loadMissing(['user', 'shop']);
            $partner = $request->user;
            if (! $partner) {
                return;
            }

            $approved = $request->status === 'approved';
            $template = $approved
                ? ($settings->reward_redemption_approved_message_template
                    ?: SmsSetting::defaultRewardRedemptionApprovedTemplate())
                : ($settings->reward_redemption_rejected_message_template
                    ?: SmsSetting::defaultRewardRedemptionRejectedTemplate());

            $placeholders = $this->rewardPlaceholders(
                $partner,
                $request->shop,
                (int) $request->requested_points,
                (int) ($partner->fresh()?->reward_points ?? $partner->reward_points ?? 0),
                [
                    'type' => (string) $request->redemption_type,
                    'status' => (string) $request->status,
                    'reason' => trim((string) ($request->rejection_reason ?: '-')),
                    'note' => trim((string) ($request->note ?: '-')),
                ],
            );

            $this->sendTemplated($settings, $this->normalizeMobile($partner->phone), $template, $placeholders, [
                'kind' => $approved ? 'reward_redemption_approved' : 'reward_redemption_rejected',
                'user_id' => $partner->id,
                'request_id' => $request->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reward redemption decision SMS failed', [
                'request_id' => $request->id,
                'status' => $request->status,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    protected function rewardPlaceholders(User $partner, ?Shop $shop, int $points, int $balance, array $extra = []): array
    {
        $type = (string) ($extra['type'] ?? '');
        $typeLabel = match ($type) {
            'cash' => 'Cash',
            'gift' => 'Gift',
            '' => '-',
            default => ucfirst($type),
        };

        return [
            '{{partner}}' => trim((string) ($partner->name ?: 'Partner')),
            '{{name}}' => trim((string) ($partner->name ?: 'Partner')),
            '{{shop}}' => $shop?->name ?: 'shop',
            '{{points}}' => (string) $points,
            '{{balance}}' => (string) $balance,
            '{{type}}' => $typeLabel,
            '{{status}}' => ucfirst((string) ($extra['status'] ?? '')),
            '{{reason}}' => (string) ($extra['reason'] ?? '-'),
            '{{note}}' => (string) ($extra['note'] ?? '-'),
            '{{source}}' => (string) ($extra['source'] ?? '-'),
            '{{customer}}' => (string) ($extra['customer'] ?? '-'),
            '{{amount}}' => (string) ($extra['amount'] ?? '-'),
            '{{order_id}}' => (string) ($extra['order_id'] ?? '-'),
            '{{mobile}}' => '',
        ];
    }

    /**
     * @param  callable(SmsSetting): bool  $enabled
     * @param  callable(SmsSetting): ?string  $template
     */
    protected function notifyAccountCreated(User $user, ?Shop $shop, callable $enabled, callable $template, string $kind): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $enabled($settings)) {
                return;
            }

            $body = trim((string) $template($settings));
            if ($body === '') {
                return;
            }

            $mobile = $this->normalizeMobile($user->phone);
            if ($mobile === null) {
                Log::info('Account SMS skipped: no mobile', [
                    'kind' => $kind,
                    'user_id' => $user->id,
                ]);

                return;
            }

            $placeholders = [
                '{{name}}' => trim((string) ($user->name ?: 'User')),
                '{{mobile}}' => $mobile,
                '{{shop}}' => $shop?->name ?: 'shop',
            ];
            $message = strtr($body, $placeholders);

            $this->dispatch($settings, $mobile, $message, $placeholders);
        } catch (\Throwable $e) {
            Log::warning('Account created SMS failed', [
                'kind' => $kind,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyOrderStatusChanged(Order $order): void
    {
        $this->notifyStatusChange(
            $order,
            enabled: fn (SmsSetting $settings) => (bool) $settings->order_status_sms_enabled,
            template: fn (SmsSetting $settings) => $settings->templateForOrderStatus((string) $order->status),
            kind: 'order_status',
        );
    }

    public function notifyPaymentStatusChanged(Order $order): void
    {
        $this->notifyStatusChange(
            $order,
            enabled: fn (SmsSetting $settings) => (bool) $settings->payment_status_sms_enabled,
            template: fn (SmsSetting $settings) => $settings->templateForPaymentStatus((string) $order->payment_status),
            kind: 'payment_status',
        );
    }

    /**
     * @param  callable(SmsSetting): bool  $enabled
     * @param  callable(SmsSetting): ?string  $template
     */
    protected function notifyStatusChange(Order $order, callable $enabled, callable $template, string $kind): void
    {
        try {
            $settings = SmsSetting::current();
            if (! $settings->is_enabled || ! $enabled($settings)) {
                return;
            }

            $body = $template($settings);
            if (! filled($body)) {
                return;
            }

            $order->loadMissing(['user', 'shop', 'address', 'items', 'partnerUser']);

            foreach ($this->recipientMobiles($order) as $mobile) {
                $placeholders = $this->orderPlaceholders($order, $mobile);
                $message = strtr($body, $placeholders);

                try {
                    $this->dispatch($settings, $mobile, $message, $placeholders);
                } catch (\Throwable $e) {
                    Log::warning('Order status SMS failed', [
                        'order_id' => $order->id,
                        'kind' => $kind,
                        'mobile' => $mobile,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Order status SMS failed', [
                'order_id' => $order->id,
                'kind' => $kind,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function recipientMobiles(Order $order): array
    {
        $mobiles = [];

        $customer = $this->resolveOrderMobile($order);
        if ($customer !== null) {
            $mobiles[$customer] = true;
        }

        $partner = $this->normalizeMobile($order->partnerUser?->phone);
        if ($partner !== null) {
            $mobiles[$partner] = true;
        }

        return array_keys($mobiles);
    }

    /**
     * @return array<string, string>
     */
    protected function orderPlaceholders(Order $order, string $mobile): array
    {
        $shopName = $order->shop?->name ?: 'shop';
        $customerName = trim((string) ($order->user?->name ?: $order->address?->name ?: 'Customer'));
        $partnerName = trim((string) ($order->partnerUser?->name ?: ''));
        if ($partnerName === '') {
            $partnerName = 'Partner';
        }

        return [
            '{{order_id}}' => (string) $order->id,
            '{{shop}}' => $shopName,
            '{{total}}' => number_format((float) $order->grand_total, 2, '.', ''),
            '{{customer}}' => $customerName,
            '{{partner}}' => $partnerName,
            '{{mobile}}' => $mobile,
            '{{items_count}}' => (string) $order->items->count(),
            '{{status}}' => ucfirst((string) $order->status),
            '{{payment_status}}' => ucfirst((string) $order->payment_status),
        ];
    }

    /**
     * @param  array<string, string>  $placeholders
     * @param  array<string, mixed>  $context
     */
    protected function sendTemplated(
        SmsSetting $settings,
        ?string $mobile,
        ?string $template,
        array $placeholders,
        array $context,
    ): void {
        $body = trim((string) $template);
        if ($body === '') {
            return;
        }

        if ($mobile === null) {
            Log::info('SMS skipped: no mobile', $context);

            return;
        }

        $placeholders['{{mobile}}'] = $mobile;
        $message = strtr($body, $placeholders);

        try {
            $this->dispatch($settings, $mobile, $message, $placeholders);
        } catch (\Throwable $e) {
            Log::warning('SMS dispatch failed', array_merge($context, [
                'mobile' => $mobile,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * @return array<string, string>
     */
    protected function offlineBillPlaceholders(OfflineBill $bill, float $closingBalance, string $mobile): array
    {
        $customerName = trim((string) ($bill->customer?->name ?: 'Customer'));
        $partnerName = trim((string) ($bill->partner?->name ?: ''));
        if ($partnerName === '') {
            $partnerName = 'Partner';
        }

        $paymentMode = match ((string) $bill->payment_mode) {
            'cash' => 'Cash',
            'upi' => 'UPI',
            'online' => 'Online',
            'cheque' => 'Cheque',
            default => $bill->payment_mode ?: '-',
        };

        return [
            '{{bill_id}}' => (string) $bill->id,
            '{{shop}}' => $bill->shop?->name ?: 'shop',
            '{{customer}}' => $customerName,
            '{{partner}}' => $partnerName,
            '{{amount}}' => number_format((float) $bill->amount, 2, '.', ''),
            '{{balance}}' => number_format($closingBalance, 2, '.', ''),
            '{{type}}' => $bill->type === 'credit' ? 'Credit' : 'Debit',
            '{{points}}' => (string) ((int) $bill->reward_points),
            '{{payment_mode}}' => $paymentMode,
            '{{remarks}}' => trim((string) ($bill->remarks ?: '-')),
            '{{mobile}}' => $mobile,
        ];
    }

    /**
     * @param  array<string, string>  $extra
     */
    protected function dispatch(SmsSetting $settings, string $mobile, string $message, array $extra = []): void
    {
        if (blank($settings->endpoint)) {
            throw new RuntimeException('SMS endpoint is not configured.');
        }

        $replacements = array_merge([
            '{{otp}}' => '',
            '{{mobile}}' => $mobile,
            '{{mobilenumber}}' => $mobile,
            '{{message}}' => $message,
        ], $extra);

        $params = [];
        foreach (($settings->payload_params ?? []) as $key => $value) {
            $params[(string) $key] = strtr((string) $value, $replacements);
        }

        $method = strtoupper((string) $settings->http_method);
        $endpoint = (string) $settings->endpoint;

        try {
            $response = match ($method) {
                'POST' => Http::asForm()->timeout(30)->post($endpoint, $params),
                default => Http::timeout(30)->get($endpoint, $params),
            };
        } catch (\Throwable) {
            throw new RuntimeException('Failed to send SMS. Please try again.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('SMS provider rejected the request.');
        }
    }

    protected function resolveOrderMobile(Order $order): ?string
    {
        return $this->firstValidMobile([
            $order->user?->phone,
            $order->address?->phone,
        ]);
    }

    /**
     * @param  list<mixed>  $candidates
     */
    protected function firstValidMobile(array $candidates): ?string
    {
        foreach ($candidates as $raw) {
            $mobile = $this->normalizeMobile($raw);
            if ($mobile !== null) {
                return $mobile;
            }
        }

        return null;
    }

    protected function normalizeMobile(mixed $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) >= 10 ? $digits : null;
    }
}
