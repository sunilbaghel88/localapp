<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Orders\OrderOnBehalfAiService;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    public function applyAiPrompt(Get $get, Set $set): void
    {
        $prompt = (string) ($get('ai_prompt') ?? '');
        $prompt = trim($prompt);

        if ($prompt === '') {
            Notification::make()
                ->title('Type what you want to add')
                ->body('Example: Add 2 Havells 5A MCB and 1 coil Finolex 1.5mm wire')
                ->warning()
                ->send();
            return;
        }

        $shopId = $get('shop_id');
        if (! $shopId) {
            Notification::make()
                ->title('Select a shop first')
                ->warning()
                ->send();
            return;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            Notification::make()
                ->title('Please sign in again')
                ->danger()
                ->send();
            return;
        }

        try {
            $result = app(OrderOnBehalfAiService::class)->suggest((int) $shopId, $prompt, $user);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Could not understand the text')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $orderItems = [];
        foreach ($result['data'] as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unitPrice = isset($row['price']) ? (float) $row['price'] : null;
            $orderItems[] = [
                'product_id' => $row['product_id'],
                'product_variant_id' => $row['variant_id'],
                'quantity' => $qty,
                'brand_name' => $row['brand'] ?? null,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice !== null ? $unitPrice * $qty : null,
            ];
        }
        $missing = $result['missing'] ?? [];

        if ($orderItems === []) {
            Notification::make()
                ->title('No products matched')
                ->body('Try using brand + item name, like "Havells MCB 5A".')
                ->danger()
                ->send();
            return;
        }

        $existing = Arr::wrap($get('order_items_data') ?? []);

        // Ensure existing rows also have display fields recomputed
        $existingHydrated = collect($existing)
            ->map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }
                $productId = $row['product_id'] ?? null;
                $variantId = $row['product_variant_id'] ?? null;
                $qty = max(1, (int) ($row['quantity'] ?? 1));

                $product = $productId ? Product::with('brand')->find($productId) : null;
                $variant = $variantId ? ProductVariant::find($variantId) : null;

                $unitPrice = $variant ? (float) $variant->price : null;
                $lineTotal = $unitPrice !== null ? $unitPrice * $qty : null;

                $row['brand_name'] = $row['brand_name'] ?? ($product?->brand?->name);
                $row['unit_price'] = $row['unit_price'] ?? $unitPrice;
                $row['line_total'] = $row['line_total'] ?? $lineTotal;

                return $row;
            })
            ->all();

        $merged = array_values(array_filter(array_merge($existingHydrated, $orderItems)));

        $set('order_items_data', $merged);

        $message = 'Added '.count($orderItems).' item(s) to the order.';
        if ($missing !== []) {
            $message .= ' Not found: '.Str::limit(implode(', ', $missing), 120);
        }

        Notification::make()
            ->title('AI suggestions applied')
            ->body($message)
            ->success()
            ->send();
    }

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

        app(\App\Services\Sms\SmsSender::class)->notifyOrderPlaced($order->fresh(['items', 'shop', 'user', 'address']));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
