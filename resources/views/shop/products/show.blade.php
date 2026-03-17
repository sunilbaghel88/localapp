@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <nav class="mb-4 text-sm">
            <a href="{{ route('home') }}" class="text-gray-500 hover:text-gray-700">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ route('products.index') }}" class="text-gray-500 hover:text-gray-700">Products</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900">{{ $product->name }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Product Images -->
            <div>
                @if($product->images->count() > 0)
                <div class="mb-4">
                    <img id="mainImage" src="{{ asset('storage/' . $product->images->first()->url) }}" alt="{{ $product->name }}" class="w-full h-96 object-cover rounded-lg">
                </div>
                @if($product->images->count() > 1)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($product->images->take(4) as $image)
                    <img src="{{ asset('storage/' . $image->url) }}" alt="{{ $product->name }}" 
                         onclick="document.getElementById('mainImage').src = this.src"
                         class="w-full h-20 object-cover rounded-lg cursor-pointer hover:opacity-75 border-2 border-transparent hover:border-amber-500">
                    @endforeach
                </div>
                @endif
                @else
                <div class="w-full h-96 bg-gray-200 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400">No image available</span>
                </div>
                @endif
            </div>

            <!-- Product Info -->
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $product->name }}</h1>
                @if($product->brand)
                <p class="text-gray-600 mb-4">Brand: {{ $product->brand->name }}</p>
                @endif

                <!-- Price -->
                @if($product->variants->count() > 0)
                @php
                    $lowestPrice = $product->variants->min('price');
                    $highestPrice = $product->variants->max('price');
                @endphp
                <div class="mb-6">
                    @if($lowestPrice === $highestPrice)
                        <span class="text-3xl font-bold text-gray-900">₹{{ number_format($lowestPrice, 2) }}</span>
                    @else
                        <span class="text-3xl font-bold text-gray-900">₹{{ number_format($lowestPrice, 2) }} - ₹{{ number_format($highestPrice, 2) }}</span>
                    @endif
                </div>
                @endif

                <!-- Description -->
                @if($product->description)
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-900 mb-2">Description</h3>
                    <p class="text-gray-600">{{ $product->description }}</p>
                </div>
                @endif

                <!-- Additional info (variant attributes) - shown when variant is selected via JS -->
                <div id="variantAttributesSection" class="mb-6 hidden">
                    <h3 class="font-semibold text-gray-900 mb-2">Additional information</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <tbody id="variantAttributesBody" class="divide-y divide-gray-200 bg-white"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Variants -->
                @if($product->variants->count() > 0)
                <form action="{{ route('cart.add') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Variant</label>
                        <select name="product_variant_id" id="variantSelect" required class="w-full border border-gray-300 rounded-lg px-4 py-2">
                            <option value="">Choose a variant</option>
                            @foreach($product->variants as $variant)
                            <option value="{{ $variant->id }}"
                                    data-price="{{ $variant->price }}"
                                    data-stock="{{ $variant->stock }}"
                                    data-compare-price="{{ $variant->compare_at_price }}"
                                    data-attributes="{{ base64_encode(json_encode($variant->attributes ?? [])) }}">
                                {{ $variant->name ?: $variant->sku }}
                                - ₹{{ number_format($variant->price, 2) }}
                                @if($variant->stock <= 0) (Out of Stock) @endif
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="variantInfo" class="hidden">
                        <div class="mb-4">
                            <span id="variantPrice" class="text-2xl font-bold text-gray-900"></span>
                            <span id="variantComparePrice" class="text-lg text-gray-500 line-through ml-2"></span>
                        </div>
                        <div id="stockInfo" class="mb-4"></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" id="quantityInput" class="w-32 border border-gray-300 rounded-lg px-4 py-2">
                    </div>

                    <button type="submit" id="addToCartBtn" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-3 rounded-lg font-semibold transition">
                        Add to Cart
                    </button>
                </form>

                <script>
                    document.getElementById('variantSelect').addEventListener('change', function() {
                        const option = this.options[this.selectedIndex];
                        const price = option.dataset.price;
                        const stock = parseInt(option.dataset.stock);
                        const comparePrice = option.dataset.comparePrice;
                        const variantInfo = document.getElementById('variantInfo');
                        const quantityInput = document.getElementById('quantityInput');
                        const addToCartBtn = document.getElementById('addToCartBtn');

                        if (this.value) {
                            variantInfo.classList.remove('hidden');
                            document.getElementById('variantPrice').textContent = '₹' + parseFloat(price).toFixed(2);

                            if (comparePrice && parseFloat(comparePrice) > parseFloat(price)) {
                                document.getElementById('variantComparePrice').textContent = '₹' + parseFloat(comparePrice).toFixed(2);
                                document.getElementById('variantComparePrice').classList.remove('hidden');
                            } else {
                                document.getElementById('variantComparePrice').classList.add('hidden');
                            }

                            var attrsSection = document.getElementById('variantAttributesSection');
                            var attrsBody = document.getElementById('variantAttributesBody');
                            var attrsEncoded = option.dataset.attributes || '';
                            try {
                                var attrs = {};
                                if (attrsEncoded) {
                                    attrs = JSON.parse(atob(attrsEncoded));
                                }
                                var filled = Object.keys(attrs).filter(function(k) { return attrs[k] !== '' && attrs[k] != null; });
                                if (filled.length > 0) {
                                    attrsBody.innerHTML = filled.map(function(k) {
                                        var label = k.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
                                        return '<tr>' +
                                            '<th class="px-4 py-2 text-left font-medium text-gray-700 w-1/3 bg-gray-50">' + label + '</th>' +
                                            '<td class="px-4 py-2 text-gray-900">' + (attrs[k] || '') + '</td>' +
                                        '</tr>';
                                    }).join('');
                                    attrsSection.classList.remove('hidden');
                                } else {
                                    attrsSection.classList.add('hidden');
                                }
                            } catch (e) {
                                attrsSection.classList.add('hidden');
                            }

                            if (stock > 0) {
                                document.getElementById('stockInfo').innerHTML = '<span class="text-green-600">In Stock (' + stock + ' available)</span>';
                                quantityInput.max = stock;
                                quantityInput.disabled = false;
                                addToCartBtn.disabled = false;
                                addToCartBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                            } else {
                                document.getElementById('stockInfo').innerHTML = '<span class="text-red-600">Out of Stock</span>';
                                quantityInput.disabled = true;
                                addToCartBtn.disabled = true;
                                addToCartBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            }
                        } else {
                            variantInfo.classList.add('hidden');
                            document.getElementById('variantAttributesSection').classList.add('hidden');
                        }
                    });
                </script>
                @else
                <div class="bg-gray-100 rounded-lg p-4 text-center">
                    <p class="text-gray-600">This product is currently unavailable.</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($relatedProducts as $relatedProduct)
                @include('shop.components.product-card', ['product' => $relatedProduct])
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
