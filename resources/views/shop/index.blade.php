@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="bg-white">
    <!-- Hero Section -->
    <div class="relative bg-gradient-to-r from-amber-500 to-amber-600 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold text-white mb-4">
                    Welcome to {{ config('app.name') }}
                </h1>
                <p class="text-xl text-amber-100 mb-8">
                    Discover amazing products from trusted sellers
                </p>
                <a href="{{ route('products.index') }}" class="inline-block bg-white text-amber-600 px-8 py-3 rounded-lg font-semibold hover:bg-amber-50 transition">
                    Shop Now
                </a>
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    @if($categories->count() > 0)
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Shop by Category</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($categories as $category)
            <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition text-center">
                <div class="text-gray-600 mb-2">{{ $category->name }}</div>
                <div class="text-sm text-gray-500">{{ $category->products_total_count ?? $category->products_count }} products</div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Featured Products Section -->
    <div class="bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Featured Products</h2>
                <a href="{{ route('products.index') }}" class="text-amber-600 hover:text-amber-700 font-medium">
                    View All →
                </a>
            </div>
            @if($featuredProducts->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                @include('shop.components.product-card', ['product' => $product])
                @endforeach
            </div>
            @else
            <p class="text-gray-500 text-center py-12">No featured products available at the moment.</p>
            @endif
        </div>
    </div>
@endsection
