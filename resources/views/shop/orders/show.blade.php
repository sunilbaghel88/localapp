@extends('layouts.app')

@section('title', 'Order #' . $order->id)

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('orders.index') }}" class="text-amber-600 hover:text-amber-700 text-sm font-medium">
                ← Back to Orders
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-8">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Order #{{ $order->id }}</h1>
                    <p class="text-gray-600 mt-1">Placed on {{ $order->created_at->format('F d, Y \a\t g:i A') }}</p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 text-sm font-semibold rounded-full 
                        {{ $order->status === 'delivered' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $order->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}
                        {{ !in_array($order->status, ['delivered', 'pending', 'cancelled']) ? 'bg-blue-100 text-blue-800' : '' }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Order Items</h2>
                <div class="space-y-4">
                    @foreach($order->items as $item)
                    <div class="flex gap-4 p-4 border border-gray-200 rounded-lg">
                        @if($item->product->images->first())
                        <img src="{{ asset('storage/' . $item->product->images->first()->url) }}" 
                             alt="{{ $item->name }}" 
                             class="w-20 h-20 object-cover rounded-lg">
                        @else
                        <div class="w-20 h-20 bg-gray-200 rounded-lg"></div>
                        @endif
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">{{ $item->name }}</h3>
                            <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }}</p>
                            <p class="text-sm text-gray-600">Price: ₹{{ number_format($item->price, 2) }} each</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-gray-900">₹{{ number_format($item->total, 2) }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Shipping Address -->
            @if($order->address)
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="font-semibold text-gray-900">{{ $order->address->name }}</p>
                    <p class="text-gray-600">{{ $order->address->address_line1 }}</p>
                    @if($order->address->address_line2)
                    <p class="text-gray-600">{{ $order->address->address_line2 }}</p>
                    @endif
                    <p class="text-gray-600">
                        {{ $order->address->city }}, {{ $order->address->state }} {{ $order->address->postal_code }}
                    </p>
                    <p class="text-gray-600">{{ $order->address->country }}</p>
                    @if($order->address->phone)
                    <p class="text-gray-600 mt-2">Phone: {{ $order->address->phone }}</p>
                    @endif
                </div>
            </div>
            @endif

            <!-- Order Summary -->
            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>
                <div class="space-y-2">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>₹{{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Shipping</span>
                        <span>₹{{ number_format($order->shipping_total, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Tax</span>
                        <span>₹{{ number_format($order->tax_total, 2) }}</span>
                    </div>
                    @if($order->discount_total > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Discount</span>
                        <span>-₹{{ number_format($order->discount_total, 2) }}</span>
                    </div>
                    @endif
                    <div class="border-t border-gray-200 pt-2 mt-2">
                        <div class="flex justify-between font-bold text-gray-900 text-lg">
                            <span>Total</span>
                            <span>₹{{ number_format($order->grand_total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
