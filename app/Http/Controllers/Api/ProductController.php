<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('status', 'published')
            ->whereHas('shop', fn ($q) => $q->on())
            ->with(['images' => function ($q) {
                $q->where('is_primary', true)->orWhereNull('is_primary')->orderBy('sort_order')->limit(1);
            }, 'variants' => function ($q) {
                $q->where('is_active', true)->orderBy('price');
            }, 'category', 'shop']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category') && $request->category !== 'featured') {
            $category = Category::query()
                ->where('slug', $request->category)
                ->where('is_active', true)
                ->first();

            if ($category) {
                if ($category->parent_id === null) {
                    $childIds = Category::query()
                        ->where('is_active', true)
                        ->where('parent_id', $category->id)
                        ->pluck('id');

                    $query->whereIn('category_id', $childIds->push($category->id));
                } else {
                    $query->where('category_id', $category->id);
                }
            }
        }

        if ($request->filled('shop')) {
            $query->whereHas('shop', function ($q) use ($request) {
                $q->where('slug', $request->shop);
            });
        }

        if ($request->filled('min_price')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('price', '>=', $request->min_price);
            });
        }
        if ($request->filled('max_price')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('price', '<=', $request->max_price);
            });
        }

        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderByRaw(
                    '(select min(pv.price) from product_variants pv where pv.product_id = products.id and pv.is_active = true) asc'
                );
                break;
            case 'price_high':
                $query->orderByRaw(
                    '(select max(pv.price) from product_variants pv where pv.product_id = products.id and pv.is_active = true) desc'
                );
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->latest();
        }

        $perPage = min((int) $request->get('per_page', 12), 50);
        $products = $query->paginate($perPage)->withQueryString();
        $productCountScope = function ($query) {
            $query->where('status', 'published')
                ->whereHas('shop', fn ($q) => $q->on());
        };

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            ])
            ->withCount(['products' => $productCountScope])
            ->orderBy('name')
            ->get()
            ->each(function (Category $parent) use ($productCountScope) {
                $parent->children->loadCount(['products' => $productCountScope]);
                $parent->products_total_count = (int) $parent->products_count + (int) $parent->children->sum('products_count');
            });

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        if ($product->status !== 'published' || ! $product->shop || $product->shop->status !== 'on') {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $product->load([
            'images' => function ($q) {
                $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
            },
            'variants' => function ($q) {
                $q->where('is_active', true)->orderBy('price');
            },
            'category',
            'shop',
        ]);

        $relatedProducts = Product::where('status', 'published')
            ->whereHas('shop', fn ($q) => $q->on())
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id)
                    ->orWhere('shop_id', $product->shop_id);
            })
            ->with(['images' => function ($q) {
                $q->where('is_primary', true)->limit(1);
            }, 'variants' => function ($q) {
                $q->where('is_active', true)->orderBy('price')->limit(1);
            }])
            ->take(4)
            ->get();

        return response()->json([
            'product' => $product,
            'related_products' => $relatedProducts,
        ]);
    }
}
