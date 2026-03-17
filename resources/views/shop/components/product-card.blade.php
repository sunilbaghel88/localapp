@php
    $primaryImage = $product->images->first();
    $lowestPriceVariant = $product->variants->first();
    $imageUrl = $primaryImage ? asset('storage/' . $primaryImage->url) : asset('images/placeholder.png');
@endphp

<div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition">
    <a href="{{ route('products.show', $product->slug) }}">
        <div class="aspect-w-1 aspect-h-1 bg-gray-200">
            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="w-full h-48 object-cover">
        </div>
    </a>
    <div class="p-4">
        @if($product->brand)
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ $product->brand->name }}</p>
        @endif
        <a href="{{ route('products.show', $product->slug) }}">
            <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">{{ $product->name }}</h3>
        </a>
        <div class="flex items-center justify-between">
            <div>
                @if($lowestPriceVariant)
                    <span class="text-lg font-bold text-gray-900">₹{{ number_format($lowestPriceVariant->price, 2) }}</span>
                    @if($lowestPriceVariant->compare_at_price && $lowestPriceVariant->compare_at_price > $lowestPriceVariant->price)
                        <span class="text-sm text-gray-500 line-through ml-2">₹{{ number_format($lowestPriceVariant->compare_at_price, 2) }}</span>
                    @endif
                @else
                    <span class="text-gray-500">Out of stock</span>
                @endif
            </div>
        </div>
        @if($lowestPriceVariant && $lowestPriceVariant->stock > 0)
        <form action="{{ route('cart.add') }}" method="POST" class="mt-4">
            @csrf
            <input type="hidden" name="product_variant_id" value="{{ $lowestPriceVariant->id }}">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-lg font-medium transition">
                Add to Cart
            </button>
        </form>
        @else
        <button disabled class="w-full bg-gray-300 text-gray-500 py-2 rounded-lg font-medium mt-4 cursor-not-allowed">
            Out of Stock
        </button>
        @endif
    </div>
</div>
