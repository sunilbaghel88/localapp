<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopProductController extends Controller
{
    protected function authorizeForShopOwnerProduct(string $ability, ?Product $product = null): void
    {
        // Let Filament/Shield-style permissions drive access.
        // ProductPolicy will enforce permission checks; we still validate ownership below.
        if ($product) {
            $this->authorize($ability, $product);
            return;
        }
        $this->authorize($ability, Product::class);
    }

    protected function ensureProductBelongsToCurrentUser(Product $product): void
    {
        if (! $product->relationLoaded('shop')) {
            $product->load('shop');
        }

        if (! $product->shop || $product->shop->user_id !== Auth::id()) {
            abort(403);
        }
    }

    protected function uniqueSlug(?int $ignoreProductId, string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (Product::query()
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeForShopOwnerProduct('viewAny');

        $query = Product::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()));

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category') && $request->category !== 'featured') {
            $categorySlug = (string) $request->category;

            $category = Category::query()
                ->where('slug', $categorySlug)
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

        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

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

        $perPage = min((int) $request->get('per_page', 12), 50);

        $products = $query->with([
            'images' => function ($q) {
                $q->where('is_primary', true)->orWhereNull('is_primary')->orderBy('sort_order')->limit(1);
            },
            'variants' => function ($q) {
                $q->where('is_active', true)->orderBy('price');
            },
            'category',
            'shop',
            'brand',
        ])->paginate($perPage)->withQueryString();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $this->ensureProductBelongsToCurrentUser($product);
        $this->authorizeForShopOwnerProduct('view', $product);

        $product->load([
            'shop',
            'category',
            'brand',
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            'variants' => fn ($q) => $q->orderBy('id'),
        ]);

        return response()->json([
            'product' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeForShopOwnerProduct('create');

        $data = $request->validate([
            'shop_id' => ['required', Rule::exists('shops', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.attributes' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'images.*.url' => ['required_with:images', 'string', 'max:2048'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $shop = Shop::query()
            ->where('user_id', $user->id)
            ->where('id', (int) $data['shop_id'])
            ->firstOrFail();

        $brandId = $data['brand_id'] ?? null;
        if (empty($brandId) && ! empty($data['brand_name'])) {
            $brand = Brand::create([
                'name' => $data['brand_name'],
                'slug' => Str::slug($data['brand_name']),
                'is_approved' => true,
                'created_by' => $user->id,
            ]);
            $brandId = $brand->getKey();
        }

        $baseSlug = ! empty($data['slug']) ? (string) $data['slug'] : Str::slug((string) $data['name']);
        $slug = $this->uniqueSlug(null, $baseSlug);

        $product = Product::create([
            'shop_id' => $shop->id,
            'category_id' => (int) $data['category_id'],
            'brand_id' => $brandId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        foreach ($data['variants'] as $variant) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $variant['sku'] ?? null,
                'name' => $variant['name'] ?? null,
                'stock' => (int) ($variant['stock'] ?? 0),
                'price' => (float) ($variant['price'] ?? 0),
                'compare_at_price' => array_key_exists('compare_at_price', $variant) ? ($variant['compare_at_price'] === null ? null : (float) $variant['compare_at_price']) : null,
                'attributes' => $variant['attributes'] ?? [],
                'is_active' => (bool) ($variant['is_active'] ?? true),
            ]);
        }

        $images = $data['images'] ?? [];
        foreach ($images as $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'url' => $image['url'],
                'product_variant_id' => null,
                'is_primary' => (bool) ($image['is_primary'] ?? false),
                'sort_order' => (int) ($image['sort_order'] ?? 0),
            ]);
        }

        $product->load([
            'shop',
            'category',
            'brand',
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            'variants' => fn ($q) => $q->orderBy('id'),
        ]);

        return response()->json([
            'product' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $user = Auth::user();

        $imagesWasProvided = $request->has('images');

        $this->ensureProductBelongsToCurrentUser($product);
        $this->authorizeForShopOwnerProduct('update', $product);

        $data = $request->validate([
            'shop_id' => ['required', Rule::exists('shops', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.attributes' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'integer'],
            'images.*.url' => ['required_with:images', 'string', 'max:2048'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $shop = Shop::query()
            ->where('user_id', $user->id)
            ->where('id', (int) $data['shop_id'])
            ->firstOrFail();

        $brandId = $data['brand_id'] ?? null;
        if (empty($brandId) && ! empty($data['brand_name'])) {
            $brand = Brand::query()->where('name', $data['brand_name'])->first();
            if (! $brand) {
                $brand = Brand::create([
                    'name' => $data['brand_name'],
                    'slug' => Str::slug($data['brand_name']),
                    'is_approved' => true,
                    'created_by' => $user->id,
                ]);
            }
            $brandId = $brand->getKey();
        }

        $baseSlug = ! empty($data['slug']) ? (string) $data['slug'] : Str::slug((string) $data['name']);
        $slug = $this->uniqueSlug($product->id, $baseSlug);

        $product->update([
            'shop_id' => $shop->id,
            'category_id' => (int) $data['category_id'],
            'brand_id' => $brandId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        // Variants: update existing by id, create new, and deactivate removed ones (avoid breaking order history).
        $incomingVariants = $data['variants'] ?? [];
        $incomingVariantIds = collect($incomingVariants)
            ->map(fn ($v) => $v['id'] ?? null)
            ->filter()
            ->values()
            ->all();

        $existingVariants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->get();

        foreach ($existingVariants as $existing) {
            if (! in_array($existing->id, $incomingVariantIds, true)) {
                $existing->update(['is_active' => false]);
            }
        }

        foreach ($incomingVariants as $variant) {
            $variantId = $variant['id'] ?? null;
            if (! empty($variantId)) {
                $existing = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('id', (int) $variantId)
                    ->firstOrFail();

                $existing->update([
                    'sku' => $variant['sku'] ?? null,
                    'name' => $variant['name'] ?? null,
                    'stock' => (int) ($variant['stock'] ?? 0),
                    'price' => (float) ($variant['price'] ?? 0),
                    'compare_at_price' => array_key_exists('compare_at_price', $variant) ? ($variant['compare_at_price'] === null ? null : (float) $variant['compare_at_price']) : null,
                    'attributes' => $variant['attributes'] ?? [],
                    'is_active' => (bool) ($variant['is_active'] ?? true),
                ]);
            } else {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variant['sku'] ?? null,
                    'name' => $variant['name'] ?? null,
                    'stock' => (int) ($variant['stock'] ?? 0),
                    'price' => (float) ($variant['price'] ?? 0),
                    'compare_at_price' => array_key_exists('compare_at_price', $variant) ? ($variant['compare_at_price'] === null ? null : (float) $variant['compare_at_price']) : null,
                    'attributes' => $variant['attributes'] ?? [],
                    'is_active' => (bool) ($variant['is_active'] ?? true),
                ]);
            }
        }

        // Images: update/create by id, and delete removed ones.
        $incomingImages = $data['images'] ?? [];
        $incomingImageIds = collect($incomingImages)
            ->map(fn ($img) => $img['id'] ?? null)
            ->filter()
            ->values()
            ->all();

        foreach ($incomingImages as $image) {
            $imageId = $image['id'] ?? null;
            if (! empty($imageId)) {
                $existing = ProductImage::query()
                    ->where('product_id', $product->id)
                    ->where('id', (int) $imageId)
                    ->firstOrFail();

                $existing->update([
                    'url' => $image['url'],
                    'is_primary' => (bool) ($image['is_primary'] ?? false),
                    'sort_order' => (int) ($image['sort_order'] ?? 0),
                ]);
            } else {
                ProductImage::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'url' => $image['url'],
                    'is_primary' => (bool) ($image['is_primary'] ?? false),
                    'sort_order' => (int) ($image['sort_order'] ?? 0),
                ]);
            }
        }

        if ($imagesWasProvided) {
            $q = ProductImage::query()->where('product_id', $product->id);
            if (count($incomingImageIds) > 0) {
                $q->whereNotIn('id', $incomingImageIds);
            }
            $q->delete();
        }

        $product->load([
            'shop',
            'category',
            'brand',
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            'variants' => fn ($q) => $q->orderBy('id'),
        ]);

        return response()->json([
            'product' => $product,
        ]);
    }
}

