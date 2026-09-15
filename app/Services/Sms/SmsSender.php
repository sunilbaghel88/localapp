<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Models\SmsSetting;
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
