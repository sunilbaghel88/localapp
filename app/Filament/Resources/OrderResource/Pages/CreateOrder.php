<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = $data['order_items_data'] ?? [];
        unset($data['order_items_data']);

        $items = array_values(array_filter($items, fn ($row) => ! empty($row['product_id']) && ! empty($row['quantity'])));

        if ($items === []) {
            throw ValidationException::withMessages([
                'order_items_data' => __('Add at least one product to the order.'),
            ]);
        }

        $subtotal = 0;
        foreach ($items as $row) {
            $variant = null;
            if (! empty($row['product_variant_id'])) {
                $variant = ProductVariant::find($row['product_variant_id']);
            }
            if (! $variant) {
                $product = Product::with(['variants' => fn ($q) => $q->where('is_active', true)])->find($row['product_id']);
                $variant = $product?->variants->first();
            }
            if (! $variant) {
                throw ValidationException::withMessages([
                    'order_items_data' => __('Each product must have an active variant with a price.'),
                ]);
            }
            $qty = (int) $row['quantity'];
            $line = $qty * (float) $variant->price;
            $subtotal += $line;
        }

        $discountTotal = 0;
        $shippingTotal = 0;
        $taxTotal = 0;
        $data['subtotal'] = round($subtotal, 2);
        $data['discount_total'] = $discountTotal;
        $data['shipping_total'] = $shippingTotal;
        $data['tax_total'] = $taxTotal;
        $data['grand_total'] = round($subtotal + $shippingTotal + $taxTotal - $discountTotal, 2);

        // Stash for afterCreate — not persisted
        $this->orderItemsPayload = $items;

        return $data;
    }

    /** @var array<int, array{product_id: int, product_variant_id?: int, quantity: int}> */
    protected array $orderItemsPayload = [];

    protected function afterCreate(): void
    {
        $order = $this->record;
        $items = $this->orderItemsPayload;
        if ($items === []) {
            return;
        }

        DB::transaction(function () use ($order, $items) {
            foreach ($items as $row) {
                $product = Product::findOrFail($row['product_id']);
                $variant = null;
                if (! empty($row['product_variant_id'])) {
                    $variant = ProductVariant::where('product_id', $product->id)
                        ->where('id', $row['product_variant_id'])
                        ->first();
                }
                if (! $variant) {
                    $variant = $product->variants()->where('is_active', true)->orderBy('id')->firstOrFail();
                }

                $qty = (int) $row['quantity'];
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
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
