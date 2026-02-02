<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cart = $this->getOrCreateCart();
        
        if (!$cart) {
            $cart = new \App\Models\Cart();
            $cart->setRelation('items', collect());
        } else {
            $cart->load('items.variant.product.images');
        }

        return view('shop.cart.index', compact('cart'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::with('product')->findOrFail($request->product_variant_id);

        // Check if product is published
        if ($variant->product->status !== 'published' || !$variant->is_active) {
            return back()->with('error', 'Product is not available.');
        }

        // Check stock
        if ($variant->stock < $request->quantity) {
            return back()->with('error', 'Insufficient stock available.');
        }

        $cart = $this->getOrCreateCart();

        // Check if item already exists in cart
        $cartItem = $cart->items()->where('product_variant_id', $request->product_variant_id)->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;
            if ($variant->stock < $newQuantity) {
                return back()->with('error', 'Insufficient stock available.');
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

        return back()->with('success', 'Product added to cart!');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Verify cart ownership
        $cart = $this->getOrCreateCart();
        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        // Check stock
        if ($cartItem->variant->stock < $request->quantity) {
            return back()->with('error', 'Insufficient stock available.');
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return back()->with('success', 'Cart updated!');
    }

    public function remove(CartItem $cartItem)
    {
        // Verify cart ownership
        $cart = $this->getOrCreateCart();
        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $cartItem->delete();

        return back()->with('success', 'Item removed from cart!');
    }

    private function getOrCreateCart()
    {
        if (Auth::check()) {
            return Cart::firstOrCreate(['user_id' => Auth::id()]);
        }

        // For guest users, use session-based cart
        $cartId = session('cart_id');
        if ($cartId) {
            $cart = Cart::find($cartId);
            if ($cart && !$cart->user_id) {
                return $cart;
            }
        }

        $cart = Cart::create();
        session(['cart_id' => $cart->id]);
        return $cart;
    }
}
