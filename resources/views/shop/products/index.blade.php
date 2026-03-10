@extends('layouts.app')

@section('title', 'Products')

@section('content')
<div class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Filters -->
            <aside class="lg:w-64 flex-shrink-0">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                    <h3 class="font-semibold text-gray-900 mb-4">Filters</h3>
                    
                    <!-- Categories -->
                    <div class="mb-6">
                        <ul class="space-y-2">
                            <li>
                                <a href="{{ route('products.index', request()->except(['category', 'page'])) }}" class="block text-sm {{ !request('category') ? 'text-amber-600 font-medium' : 'text-gray-600' }}">
                                    All Categories
                                </a>
                            </li>

                            @foreach($categories as $parent)
                                <li>
                                    <a href="{{ route('products.index', array_merge(request()->except(['page']), ['category' => $parent->slug])) }}"
                                       class="block text-sm {{ request('category') === $parent->slug ? 'text-amber-600 font-medium' : 'text-gray-700' }}">
                                        {{ $parent->name }} ({{ $parent->products_total_count ?? $parent->products_count }})
                                    </a>

                                    @if($parent->children && $parent->children->count() > 0)
                                        <ul class="mt-2 space-y-2 ml-4 pl-3 border-l border-gray-200"
                                            style="margin-left: 1rem; padding-left: .75rem; border-left: 1px solid #e5e7eb;">
                                            @foreach($parent->children as $child)
                                                <li>
                                                    <a href="{{ route('products.index', array_merge(request()->except(['page']), ['category' => $child->slug])) }}"
                                                       class="block text-sm {{ request('category') === $child->slug ? 'text-amber-600 font-medium' : 'text-gray-600' }}">
                                                        {{ $child->name }} ({{ $child->products_count }})
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Price Range -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Price Range</h4>
                        <form method="GET" action="{{ route('products.index') }}" class="space-y-3">
                            @foreach(request()->except(['min_price', 'max_price']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <div class="flex gap-2">
                                <input type="number" name="min_price" placeholder="Min" value="{{ request('min_price') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <input type="number" name="max_price" placeholder="Max" value="{{ request('max_price') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                            <button type="submit" class="w-full bg-amber-500 text-white py-2 rounded-lg text-sm font-medium">Apply</button>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- Products Grid -->
            <main class="flex-1">
                <!-- Sort and Results -->
                <div class="flex justify-between items-center mb-6">
                    <p class="text-gray-600">
                        Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
                    </p>
                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                        @foreach(request()->except('sort') as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <label class="text-sm text-gray-700">Sort:</label>
                        <select name="sort" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Latest</option>
                            <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name A-Z</option>
                        </select>
                    </form>
                </div>

                @if($products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($products as $product)
                    @include('shop.components.product-card', ['product' => $product])
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
                @else
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <p class="text-gray-500 text-lg">No products found.</p>
                    <a href="{{ route('products.index') }}" class="text-amber-600 hover:text-amber-700 mt-4 inline-block">Clear filters</a>
                </div>
                @endif
            </main>
        </div>
    </div>
</div>
@endsection
