<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ShopMetaController extends Controller
{
    public function shops(): JsonResponse
    {
        $shops = Shop::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return response()->json([
            'shops' => $shops,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function brands(): JsonResponse
    {
        $brands = Brand::query()
            ->where('is_approved', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'brands' => $brands,
        ]);
    }
}

