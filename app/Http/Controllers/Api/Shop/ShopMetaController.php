<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopType;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ShopMetaController extends Controller
{
    public function shops(): JsonResponse
    {
        $shops = Shop::query()
            ->where('user_id', Auth::id())
            ->with(['shopType.rewardUserTypes'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'shops' => $shops,
        ]);
    }

    public function shopTypes(): JsonResponse
    {
        $shopTypes = ShopType::query()
            ->where('is_active', true)
            ->with(['rewardUserTypes'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'supports_partner_rewards']);

        return response()->json([
            'shop_types' => $shopTypes,
        ]);
    }

    public function states(): JsonResponse
    {
        $states = State::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'states' => $states,
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

