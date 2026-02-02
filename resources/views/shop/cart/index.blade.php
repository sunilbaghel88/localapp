@extends('layouts.app')

@section('title', 'Shopping Cart')

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        @if($cart->items->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm">
                    @foreach($cart->items as $item)
                    <div class="p-6 border-b border-gray-200 last:border-b-0">
                        <div class="flex gap-4">
                            @if($item->variant->product->images->first())
                            <img src="{{ asset('storage/' . $item->variant->product->images->first()->url) }}" 
                                 alt="{{ $item->variant->product->name }}" 
                                 class="w-24 h-24 object-cover rounded-lg">
                            @else
                            <div class="w-24 h-24 bg-gray-200 rounded-lg"></div>
                            @endif
                            
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900">{{ $item->variant->product->name }}</h3>
                                @if($item->variant->name)
                                <p class="text-sm text-gray-600">{{ $item->variant->name }}</p>
                                @endif
                                <p class="text-sm text-gray-500">SKU: {{ $item->variant->sku }}</p>
                                
                                <div class="mt-4 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <form action="{{ route('cart.update', $item) }}" method="POST" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-sm text-gray-700">Qty:</label>
                                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ $item->variant->stock }}" 
                                                   class="w-20 border border-gray-300 rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                                        </form>
                                        
                                        <form action="{{ route('cart.remove', $item) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-700 text-sm">Remove</button>
                                        </form>
                                    </div>
                                    
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900">₹{{ number_format($item->quantity * $item->price, 2) }}</p>
                                        <p class="text-sm text-gray-500">₹{{ number_format($item->price, 2) }} each</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>
                    
                    @php
                        $subtotal = $cart->items->sum(function($item) { return $item->quantity * $item->price; });
                        $shipping = 0;
                        $tax = 0;
                        $total = $subtotal + $shipping + $tax;
                    @endphp

                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span>₹{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span>₹{{ number_format($shipping, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Tax</span>
                            <span>₹{{ number_format($tax, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 mt-2">
                            <div class="flex justify-between font-bold text-gray-900">
                                <span>Total</span>
                                <span>₹{{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @auth
                    <a href="{{ route('checkout.index') }}" class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center py-3 rounded-lg font-semibold transition">
                        Proceed to Checkout
                    </a>
                    @else
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600 text-center mb-2">Please login to checkout</p>
                        <a href="{{ route('login') }}" class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center py-3 rounded-lg font-semibold transition">
                            Login to Checkout
                        </a>
                    </div>
                    @endauth

                    <a href="{{ route('products.index') }}" class="block text-center text-amber-600 hover:text-amber-700 mt-4 text-sm">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Start adding some products to your cart!</p>
            <a href="{{ route('products.index') }}" class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-6 py-3 rounded-lg font-semibold transition">
                Browse Products
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
