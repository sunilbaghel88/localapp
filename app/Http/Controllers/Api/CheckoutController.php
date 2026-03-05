<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index(): JsonResponse
    {
        $cart = Cart::where('user_id', Auth::id())
            ->with('items.variant.product.shop')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'cart' => null,
                'addresses' => [],
                'subtotal' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => 0,
                'message' => 'Your cart is empty.',
            ]);
        }

        $addresses = Auth::user()->addresses()->orderBy('is_default', 'desc')->get();

        $shopsWithElectricianSupport = $cart->items
            ->groupBy(fn ($item) => $item->variant->product->shop_id)
            ->keys()
            ->map(fn ($shopId) => \App\Models\Shop::with(['shopType', 'electricians'])->find($shopId))
            ->filter(fn ($shop) => $shop
                && $shop->shopType
                && $shop->shopType->supports_electrician_rewards
                && $shop->shopType->electrician_user_type_id)
            ->values();

        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $item->price);
        $shippingTotal = 0;
        $taxTotal = 0;
        $discountTotal = 0;
        $grandTotal = $subtotal + $shippingTotal + $taxTotal - $discountTotal;

        return response()->json([
            'cart' => $cart,
            'addresses' => $addresses,
            'shops_with_electrician_support' => $shopsWithElectricianSupport,
            'subtotal' => round($subtotal, 2),
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'grand_total' => round($grandTotal, 2),
        ]);
    }

    public function storeAddress(Request $request): JsonResponse
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

        if (Auth::user()->addresses()->count() === 1) {
            $address->update(['is_default' => true]);
        }

        return response()->json([
            'success' => true,
            'address' => $address,
            'message' => 'Address added successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
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
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $address = Address::where('id', $request->address_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $itemsByShop = $cart->items->groupBy(fn ($item) => $item->variant->product->shop_id);
            $createdOrders = [];

            foreach ($itemsByShop as $shopId => $items) {
                $subtotal = $items->sum(fn ($item) => $item->quantity * (float) $item->price);
                $shippingTotal = 0;
                $taxTotal = 0;
                $discountTotal = 0;
                $grandTotal = $subtotal + $shippingTotal + $taxTotal - $discountTotal;

                $electricianUserId = ($request->electrician ?? [])[$shopId] ?? null;

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

                foreach ($items as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->variant->product_id,
                        'product_variant_id' => $cartItem->product_variant_id,
                        'name' => $cartItem->variant->product->name . ($cartItem->variant->name ? ' - ' . $cartItem->variant->name : ''),
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'discount' => 0,
                        'total' => $cartItem->quantity * (float) $cartItem->price,
                        'attributes' => $cartItem->variant->attributes ?? [],
                    ]);
                    $cartItem->variant->decrement('stock', $cartItem->quantity);
                }

                $createdOrders[] = $order->load('shop', 'items.variant.product');
            }

            $cart->items()->delete();
            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully.',
                'orders' => $createdOrders,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to place order. Please try again.'], 500);
        }
    }
}
