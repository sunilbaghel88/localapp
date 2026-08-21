<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function index()
    {
        $cart = Cart::where('user_id', Auth::id())
            ->with('items.variant.product.shop')
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $addresses = Auth::user()->addresses()->orderBy('is_default', 'desc')->get();

        // Shops in cart that support partner rewards (optional partner select per shop)
        $shopsWithPartnerSupport = $cart->items
            ->groupBy(fn ($item) => $item->variant->product->shop_id)
            ->keys()
            ->map(fn ($shopId) => \App\Models\Shop::with(['shopType.rewardUserTypes', 'partners.userTypes'])->find($shopId))
            ->filter(fn ($shop) => $shop
                && $shop->shopType
                && $shop->shopType->supportsPartnerRewards())
            ->values();

        // Calculate totals
        $subtotal = $cart->items->sum(function ($item) {
            return $item->quantity * $item->price;
        });

        $shippingTotal = 0; // Can be calculated based on address
        $taxTotal = 0; // Can be calculated based on location
        $discountTotal = 0; // Can be applied from coupons
        $grandTotal = $subtotal + $shippingTotal + $taxTotal - $discountTotal;

        return view('shop.checkout.index', compact('cart', 'addresses', 'shopsWithPartnerSupport', 'subtotal', 'shippingTotal', 'taxTotal', 'discountTotal', 'grandTotal'));
    }

    public function storeAddress(Request $request)
    {
        $request->validate([
            'label' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        // If this is set as default, unset other defaults
        if ($request->is_default) {
            Auth::user()->addresses()->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => Auth::id(),
            'label' => $request->label,
            'name' => $request->name,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'is_default' => $request->is_default ?? false,
        ]);

        // If this is the first address, make it default
        if (Auth::user()->addresses()->count() === 1) {
            $address->update(['is_default' => true]);
        }

        // Return JSON for AJAX requests or when Accept header includes application/json
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'address' => $address,
                'message' => 'Address added successfully!'
            ]);
        }

        return back()->with('success', 'Address added successfully!');
    }

    public function store(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'electrician' => 'nullable|array',
            'electrician.*' => 'nullable|exists:users,id',
        ], [
            'address_id.required' => 'Please select a shipping address.',
            'address_id.exists' => 'The selected address is invalid.',
        ]);

        $cart = Cart::where('user_id', Auth::id())
            ->with('items.variant.product.shop')
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Verify address belongs to user
        $address = Address::where('id', $request->address_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        DB::beginTransaction();
        try {
            // Group items by shop
            $itemsByShop = $cart->items->groupBy(function ($item) {
                return $item->variant->product->shop_id;
            });

            foreach ($itemsByShop as $shopId => $items) {
                // Calculate totals for this shop
                $subtotal = $items->sum(function ($item) {
                    return $item->quantity * $item->price;
                });

                $shippingTotal = 0;
                $taxTotal = 0;
                $discountTotal = 0;
                $grandTotal = $subtotal + $shippingTotal + $taxTotal - $discountTotal;

                // Electrician for this shop (optional)
                $electricianUserId = ($request->electrician ?? [])[$shopId] ?? null;

                // Create order
                $order = Order::create([
                    'user_id' => Auth::id(),
                    'shop_id' => $shopId,
                    'address_id' => $address->id,
                    'electrician_user_id' => $electricianUserId,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'shipping_total' => $shippingTotal,
                    'tax_total' => $taxTotal,
                    'grand_total' => $grandTotal,
                ]);

                // Create order items
                foreach ($items as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->variant->product_id,
                        'product_variant_id' => $cartItem->product_variant_id,
                        'name' => $cartItem->variant->product->name . ($cartItem->variant->name ? ' - ' . $cartItem->variant->name : ''),
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'discount' => 0,
                        'total' => $cartItem->quantity * $cartItem->price,
                        'attributes' => $cartItem->variant->attributes ?? [],
                    ]);

                    // Update stock
                    $cartItem->variant->decrement('stock', $cartItem->quantity);
                }
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            // Redirect to first order success page (or create a combined success page)
            $firstOrder = Order::where('user_id', Auth::id())
                ->latest()
                ->first();

            return redirect()->route('checkout.success', $firstOrder)->with('success', 'Order placed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to place order. Please try again.');
        }
    }

    public function success(Order $order)
    {
        // Verify order belongs to user
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        return view('shop.checkout.success', compact('order'));
    }
}
