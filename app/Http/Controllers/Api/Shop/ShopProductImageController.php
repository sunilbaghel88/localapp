<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
}
