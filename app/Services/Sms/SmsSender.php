<?php

namespace App\Services\Sms;

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

        if (blank($settings->endpoint)) {
            throw new RuntimeException('SMS endpoint is not configured.');
        }

        $message = str_replace(
            ['{{otp}}', '{{mobile}}'],
            [$otp, $mobile],
            (string) $settings->message_template
        );

        $replacements = [
            '{{otp}}' => $otp,
            '{{mobile}}' => $mobile,
            '{{mobilenumber}}' => $mobile,
            '{{message}}' => $message,
        ];

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
        } catch (\Throwable $e) {
            Log::error('SMS API request failed', [
                'endpoint' => $endpoint,
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to send SMS. Please try again.');
        }

        if (! $response->successful()) {
            Log::warning('SMS API returned non-success status', [
                'endpoint' => $endpoint,
                'mobile' => $mobile,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('SMS provider rejected the request.');
        }

        Log::info('SMS OTP sent', [
            'mobile' => $mobile,
            'status' => $response->status(),
        ]);
    }
}
