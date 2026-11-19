<?php

namespace App\Services\Products;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageGenerationService
{
    public function generate(string $name, ?string $brand = null): string
    {
        $name = trim($name);
        $brand = $this->nullable($brand);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => __('Enter the product name first.'),
            ]);
        }

        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'name' => __('Image generation is not configured. Add OPENAI_API_KEY.'),
            ]);
        }

        @set_time_limit(120);

        $model = (string) config('services.openai.image_model', 'gpt-image-1.5');
        $payload = $this->requestPayload($model, $this->prompt($name, $brand));

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(90)
                ->post('https://api.openai.com/v1/images/generations', $payload);
        } catch (\Throwable $e) {
            Log::warning('Product image AI request failed', [
                'message' => $e->getMessage(),
                'name' => $name,
            ]);

            throw ValidationException::withMessages([
                'name' => __('Could not generate an image. Try again.'),
            ]);
        }

        if ($response->failed()) {
            Log::warning('Product image AI rejected the request', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500, ''),
                'name' => $name,
            ]);

            throw ValidationException::withMessages([
                'name' => $this->errorMessage($response->json(), $response->status()),
            ]);
        }

        $bytes = $this->imageBytes($response->json());
        $path = 'product-images/'.Str::uuid().'.'.$this->extension($bytes);
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestPayload(string $model, string $prompt): array
    {
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => (string) config('services.openai.image_size', '1024x1024'),
        ];

        $quality = trim((string) config('services.openai.image_quality', 'low'));
        if ($quality !== '') {
            $payload['quality'] = $quality;
        }

        if ($this->isGptImageModel($model)) {
            $payload['output_format'] = 'png';
        }

        return $payload;
    }

    protected function prompt(string $name, ?string $brand): string
    {
        $brandLine = $brand !== null ? "Brand: {$brand}\n" : "Brand: unbranded\n";

        return <<<PROMPT
Create a realistic product catalog photograph for an Indian hardware and electrical shop.

Product: {$name}
{$brandLine}
Show a single physical product on a clean white studio background, evenly lit, sharp focus, commercial e-commerce style.
Do not include people, hands, text, watermarks, price tags, or invented packaging labels.
The product should look like the real item a shopper would recognize from the name.
PROMPT;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function imageBytes(?array $json): string
    {
        $item = $json['data'][0] ?? null;
        if (! is_array($item)) {
            throw ValidationException::withMessages([
                'name' => __('AI returned an unexpected image response. Try again.'),
            ]);
        }

        $b64 = $item['b64_json'] ?? null;
        if (is_string($b64) && $b64 !== '') {
            $bytes = base64_decode($b64, true);
            if ($bytes === false || $bytes === '') {
                throw ValidationException::withMessages([
                    'name' => __('AI returned an invalid image. Try again.'),
                ]);
            }

            return $bytes;
        }

        $url = $item['url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw ValidationException::withMessages([
                'name' => __('AI returned an unexpected image response. Try again.'),
            ]);
        }

        try {
            $download = Http::timeout(60)->get($url);
        } catch (\Throwable $e) {
            Log::warning('Product image AI download failed', [
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'name' => __('Could not download the generated image. Try again.'),
            ]);
        }

        if ($download->failed() || $download->body() === '') {
            throw ValidationException::withMessages([
                'name' => __('Could not download the generated image. Try again.'),
            ]);
        }

        return $download->body();
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function errorMessage(?array $json, int $status): string
    {
        $message = is_array($json['error'] ?? null)
            ? trim((string) ($json['error']['message'] ?? ''))
            : '';

        if ($status === 401) {
            return __('OpenAI rejected the API key. Update OPENAI_API_KEY and try again.');
        }

        if ($status === 429) {
            return __('Image generation is busy or over quota. Try again in a minute.');
        }

        if ($message !== '') {
            return __('Could not generate an image: :error', [
                'error' => Str::limit($message, 180, ''),
            ]);
        }

        return __('Could not generate an image. Try again.');
    }

    protected function extension(string $bytes): string
    {
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'png';
        }

        if (str_starts_with($bytes, "\xFF\xD8")) {
            return 'jpg';
        }

        if (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')) {
            return 'webp';
        }

        return 'png';
    }

    protected function isGptImageModel(string $model): bool
    {
        return str_starts_with(strtolower($model), 'gpt-image');
    }

    protected function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
