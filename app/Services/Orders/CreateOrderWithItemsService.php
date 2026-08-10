<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrderWithItemsService
{
    /**
     * @param  array<int, array{product_id: int, product_variant_id?: int|null, quantity: int}>  $items
     */
    public function create(
        int $customerUserId,
        int $shopId,
        ?int $addressId,
        ?int $electricianUserId,
        string $deliveryMethod,
        ?int $deliveryAgentUserId,
        float $deliveryCharge,
        array $items,
    ): Order {
        $items = array_values(array_filter($items, fn ($row) => ! empty($row['product_id']) && ! empty($row['quantity'])));

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => [__('Add at least one product to the order.')],
            ]);
        }

        $subtotal = 0.0;

        foreach ($items as $row) {
            $variant = $this->resolveVariant((int) $row['product_id'], isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null, $shopId);
            $qty = max(1, (int) $row['quantity']);
            $subtotal += $qty * (float) $variant->price;
        }

        $discountTotal = 0;
        $shippingTotal = $deliveryMethod === 'home_delivery'
            ? max(0, round($deliveryCharge, 2))
            : 0;
        $taxTotal = 0;
        $grandTotal = round($subtotal + $shippingTotal + $taxTotal - $discountTotal, 2);

        return DB::transaction(function () use (
            $customerUserId,
            $shopId,
            $addressId,
            $electricianUserId,
            $deliveryMethod,
            $deliveryAgentUserId,
            $deliveryCharge,
            $items,
            $subtotal,
            $discountTotal,
            $shippingTotal,
            $taxTotal,
            $grandTotal,
        ) {
            $order = Order::create([
                'user_id' => $customerUserId,
                'shop_id' => $shopId,
                'address_id' => $addressId,
                'delivery_method' => $deliveryMethod,
                'electrician_user_id' => $electricianUserId,
                'delivery_agent_user_id' => $deliveryAgentUserId,
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => round($subtotal, 2),
                'discount_total' => $discountTotal,
                'shipping_total' => $shippingTotal,
                'delivery_charge' => round(max(0, $deliveryCharge), 2),
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ]);

            foreach ($items as $row) {
                $product = Product::findOrFail((int) $row['product_id']);
                if ((int) $product->shop_id !== $shopId) {
                    throw ValidationException::withMessages([
                        'items' => [__('Each product must belong to the selected shop.')],
                    ]);
                }

                $variant = $this->resolveVariant((int) $row['product_id'], isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null, $shopId);
                $qty = max(1, (int) $row['quantity']);
                $price = (float) $variant->price;
                $discount = 0;
                $total = round($qty * $price - $discount, 2);

                $name = $product->name.($variant->name ? ' - '.$variant->name : '');

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'name' => $name,
                    'quantity' => $qty,
                    'price' => $price,
                    'discount' => $discount,
                    'total' => $total,
                    'attributes' => $variant->attributes ?? [],
                ]);

                $variant->decrement('stock', $qty);
            }

            return $order->fresh(['items', 'shop', 'user']);
        });
    }

    protected function resolveVariant(int $productId, ?int $variantId, int $shopId): ProductVariant
    {
        $product = Product::query()->where('id', $productId)->where('shop_id', $shopId)->first();
        if (! $product) {
            throw ValidationException::withMessages([
                'items' => [__('Invalid product for this shop.')],
            ]);
        }

        if ($variantId) {
            $variant = ProductVariant::query()
                ->where('product_id', $productId)
                ->where('id', $variantId)
                ->where('is_active', true)
                ->first();
            if (! $variant) {
                throw ValidationException::withMessages([
                    'items' => [__('Invalid variant for the selected product.')],
                ]);
            }

            return $variant;
        }

        $variant = $product->variants()->where('is_active', true)->orderBy('id')->first();
        if (! $variant) {
            throw ValidationException::withMessages([
                'items' => [__('Each product must have an active variant with a price.')],
            ]);
        }

        return $variant;
    }
}
