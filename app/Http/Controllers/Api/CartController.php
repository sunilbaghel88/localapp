<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(): JsonResponse
    {
        $cart = Cart::where('user_id', Auth::id())->first();

        if (!$cart) {
            $cart = new Cart(['user_id' => Auth::id()]);
            $cart->setRelation('items', collect());
        } else {
            $cart->load('items.variant.product.images');
        }

        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $item->price);

        return response()->json([
            'cart' => $cart,
            'subtotal' => round($subtotal, 2),
            'items_count' => $cart->items->count(),
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::with('product')->findOrFail($request->product_variant_id);

        if ($variant->product->status !== 'published' || !$variant->is_active) {
            return response()->json(['message' => 'Product is not available.'], 422);
        }

        if ($variant->stock < $request->quantity) {
            return response()->json(['message' => 'Insufficient stock available.'], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        $cartItem = $cart->items()->where('product_variant_id', $request->product_variant_id)->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;
            if ($variant->stock < $newQuantity) {
                return response()->json(['message' => 'Insufficient stock available.'], 422);
            }
            $cartItem->update([
                'quantity' => $newQuantity,
                'price' => $variant->price,
            ]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $request->product_variant_id,
                'quantity' => $request->quantity,
                'price' => $variant->price,
            ]);
        }

        $cart->load('items.variant.product.images');
        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $item->price);

        return response()->json([
            'message' => 'Product added to cart.',
            'cart' => $cart,
            'subtotal' => round($subtotal, 2),
            'items_count' => $cart->items->count(),
        ]);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('user_id', Auth::id())->firstOrFail();
        if ($cartItem->cart_id !== $cart->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($cartItem->variant->stock < $request->quantity) {
            return response()->json(['message' => 'Insufficient stock available.'], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);
        $cart->load('items.variant.product.images');
        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $item->price);

        return response()->json([
            'message' => 'Cart updated.',
            'cart' => $cart,
            'subtotal' => round($subtotal, 2),
            'items_count' => $cart->items->count(),
        ]);
    }

    public function remove(CartItem $cartItem): JsonResponse
    {
        $cart = Cart::where('user_id', Auth::id())->firstOrFail();
        if ($cartItem->cart_id !== $cart->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $cartItem->delete();
        $cart->load('items.variant.product.images');
        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $item->price);

        return response()->json([
            'message' => 'Item removed from cart.',
            'cart' => $cart,
            'subtotal' => round($subtotal, 2),
            'items_count' => $cart->items->count(),
        ]);
    }
}
