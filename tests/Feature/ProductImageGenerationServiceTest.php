<?php

use App\Services\Products\ProductImageGenerationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.image_model', 'gpt-image-1.5');
    config()->set('services.openai.image_size', '1024x1024');
    config()->set('services.openai.image_quality', 'low');
});

test('it stores a generated product image from base64', function () {
    $png = "\x89PNG\r\n\x1a\n".'fake';

    Http::fake([
        'https://api.openai.com/v1/images/generations' => Http::response([
            'data' => [
                ['b64_json' => base64_encode($png)],
            ],
        ], 200),
    ]);

    $path = app(ProductImageGenerationService::class)->generate('Supreme CPVC Pipe', 'Supreme');

    expect($path)->toStartWith('product-images/')
        ->and($path)->toEndWith('.png');
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->get($path))->toBe($png);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request['model'] === 'gpt-image-1.5'
            && $request['quality'] === 'low'
            && ($request['output_format'] ?? null) === 'png'
            && ! array_key_exists('response_format', $request->data())
            && str_contains((string) $request['prompt'], 'Supreme CPVC Pipe')
            && str_contains((string) $request['prompt'], 'Brand: Supreme');
    });
});

test('it fails when openai is not configured', function () {
    config()->set('services.openai.api_key', '');

    app(ProductImageGenerationService::class)->generate('Pipe');
})->throws(ValidationException::class);
