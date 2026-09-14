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

            $shopName = $order->shop?->name ?: 'shop';
            $customerName = trim((string) ($order->user?->name ?: $order->address?->name ?: 'Customer'));
            $total = number_format((float) $order->grand_total, 2, '.', '');
            $itemsCount = (string) $order->items->count();

            $message = strtr((string) ($settings->order_message_template ?: 'Your order #{{order_id}} at {{shop}} is placed. Amount Rs {{total}}. Thank you.'), [
                '{{order_id}}' => (string) $order->id,
                '{{shop}}' => $shopName,
                '{{total}}' => $total,
                '{{customer}}' => $customerName,
                '{{mobile}}' => $mobile,
                '{{items_count}}' => $itemsCount,
            ]);

            $this->dispatch($settings, $mobile, $message, [
                '{{order_id}}' => (string) $order->id,
                '{{shop}}' => $shopName,
                '{{total}}' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Order SMS failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
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
        $candidates = [
            $order->user?->phone,
            $order->address?->phone,
        ];

        foreach ($candidates as $raw) {
            $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
            if (strlen($digits) > 10) {
                $digits = substr($digits, -10);
            }
            if (strlen($digits) >= 10) {
                return $digits;
            }
        }

        return null;
    }
}
