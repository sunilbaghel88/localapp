<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
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

        $categories = \App\Models\Category::where('is_active', true)
            ->withCount(['products' => function ($query) {
                $query->where('status', 'published')
                    ->whereHas('shop', fn ($q) => $q->on());
            }])
            ->take(6)
            ->get();

        return view('shop.index', compact('featuredProducts', 'categories'));
    }
}
