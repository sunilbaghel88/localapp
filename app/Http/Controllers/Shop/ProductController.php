<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('status', 'published')
            ->whereHas('shop', fn ($q) => $q->on())
            ->with([
                'images' => function ($q) {
                    $q->where('is_primary', true)->orWhereNull('is_primary')->orderBy('sort_order')->limit(1);
                },
                'variants' => function ($q) {
                    $q->where('is_active', true)->orderBy('price');
                },
                'category',
                'shop',
                'brand',
            ]);

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Category filter
        if ($request->filled('category')) {
            if ($request->category === 'featured') {
                // Featured products logic can be added here
            } else {
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
        }

        // Shop filter
        if ($request->filled('shop')) {
            $query->whereHas('shop', function ($q) use ($request) {
                $q->where('slug', $request->shop);
            });
        }

        // Price range filter
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

        // Sort
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderByRaw(
                    '(select min(pv.price) from product_variants pv where pv.product_id = products.id and pv.is_active = true) asc nulls last'
                );
                break;
            case 'price_high':
                $query->orderByRaw(
                    '(select max(pv.price) from product_variants pv where pv.product_id = products.id and pv.is_active = true) desc nulls last'
                );
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->latest();
        }

        $products = $query->paginate(12)->withQueryString();
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

        return view('shop.products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        if ($product->status !== 'published' || ! $product->shop || $product->shop->status !== 'on') {
            abort(404);
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
            'brand',
        ]);

        // Related products (only from shops that are On)
        $relatedProducts = Product::where('status', 'published')
            ->whereHas('shop', fn ($q) => $q->on())
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id)
                  ->orWhere('shop_id', $product->shop_id);
            })
            ->with([
                'images' => function ($q) {
                    $q->where('is_primary', true)->limit(1);
                },
                'variants' => function ($q) {
                    $q->where('is_active', true)->orderBy('price')->limit(1);
                },
                'brand',
            ])
            ->take(4)
            ->get();

        return view('shop.products.show', compact('product', 'relatedProducts'));
    }
}
