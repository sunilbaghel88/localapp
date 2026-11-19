<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Products\ProductImageGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopProductImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:15120', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $file = $request->file('image');
        $path = $file->store('product-images', 'public');

        return response()->json([
            'url' => $path,
        ], 201);
    }

    public function generate(Request $request, ProductImageGenerationService $images): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $images->generate(
            $data['name'],
            $data['brand'] ?? null,
        );

        return response()->json([
            'url' => $path,
        ], 201);
    }
}
