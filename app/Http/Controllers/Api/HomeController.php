<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        $featuredProducts = Product::where('status', 'published')
            ->whereHas('shop', fn ($q) => $q->on())
            ->with(['images' => function ($query) {
                $query->where('is_primary', true)->orWhereNull('is_primary')->orderBy('sort_order');
            }, 'variants' => function ($query) {
                $query->where('is_active', true)->orderBy('price');
            }])
            ->latest()
            ->take(8)
            ->get();

        $categories = Category::where('is_active', true)
            ->withCount(['products' => function ($query) {
                $query->where('status', 'published')
                    ->whereHas('shop', fn ($q) => $q->on());
            }])
            ->take(6)
            ->get();

        return response()->json([
            'featured_products' => $featuredProducts,
            'categories' => $categories,
        ]);
    }
}
