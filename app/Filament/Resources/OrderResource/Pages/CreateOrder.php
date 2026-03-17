<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
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

        $requested = $this->parsePromptIntoItems($prompt);
        if ($requested === []) {
            Notification::make()
                ->title('Could not understand the text')
                ->body('Try something like: "2 Havells MCB 5A, 1 Finolex wire 1.5mm"')
                ->danger()
                ->send();
            return;
        }

        $orderItems = [];
        $missing = [];

        foreach ($requested as $req) {
            $term = $req['term'];
            $qty = max(1, (int) $req['quantity']);

            $match = $this->findBestProductMatch(shopId: (int) $shopId, term: $term);

            if (! $match) {
                $missing[] = $term;
                continue;
            }

            $orderItems[] = [
                'product_id' => $match['product_id'],
                'product_variant_id' => $match['product_variant_id'],
                'quantity' => $qty,
            ];
        }

        if ($orderItems === []) {
            Notification::make()
                ->title('No products matched')
                ->body('Try using brand + item name, like "Havells MCB 5A".')
                ->danger()
                ->send();
            return;
        }

        $existing = Arr::wrap($get('order_items_data') ?? []);
        $merged = array_values(array_filter(array_merge($existing, $orderItems)));

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

    /**
     * @return array<int, array{quantity:int, term:string}>
     */
    protected function parsePromptIntoItems(string $prompt): array
    {
        if (class_exists(\LarAgent\Agent::class) && class_exists(\App\AiAgents\OrderItemsParserAgent::class)) {
            try {
                /** @var string $raw */
                $raw = \App\AiAgents\OrderItemsParserAgent::forUser(auth()->user())->respond($prompt);
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return collect($decoded)
                        ->filter(fn ($row) => is_array($row) && isset($row['term']))
                        ->map(fn ($row) => [
                            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                            'term' => trim((string) ($row['term'] ?? '')),
                        ])
                        ->filter(fn ($row) => $row['term'] !== '')
                        ->values()
                        ->all();
                }
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('AI error')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        $normalized = preg_replace('/\s+/', ' ', trim($prompt)) ?? $prompt;
        $normalized = preg_replace('/^add\s+/i', '', $normalized) ?? $normalized;

        // Split by " and " / "," / "&"
        $parts = preg_split('/\s*(?:,|&|\band\b|\+)\s*/i', $normalized) ?: [];
        $items = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            // Match: "2 Havells 5A MCB", or "1 coil Finolex wire"
            if (preg_match('/^(?<qty>\d+)\s+(?<term>.+)$/u', $part, $m)) {
                $items[] = [
                    'quantity' => (int) $m['qty'],
                    'term' => trim((string) $m['term']),
                ];
                continue;
            }

            $items[] = [
                'quantity' => 1,
                'term' => $part,
            ];
        }

        return array_values(array_filter($items, fn ($i) => ! empty($i['term'])));
    }

    /**
     * @return array{product_id:int, product_variant_id:int}|null
     */
    protected function findBestProductMatch(int $shopId, string $term): ?array
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $product = Product::query()
            ->where('shop_id', $shopId)
            ->whereRaw('LOWER(name) like ?', ['%'.strtolower($term).'%'])
            ->orderBy('name')
            ->first();

        if (! $product) {
            // try tokenized fallback: match any significant tokens
            $tokens = collect(preg_split('/\s+/', strtolower($term)) ?: [])
                ->map(fn ($t) => trim($t))
                ->filter(fn ($t) => $t !== '' && strlen($t) >= 3)
                ->values()
                ->all();

            if ($tokens === []) {
                return null;
            }

            $product = Product::query()
                ->where('shop_id', $shopId)
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhereRaw('LOWER(name) like ?', ['%'.$t.'%']);
                    }
                })
                ->orderBy('name')
                ->first();
        }

        if (! $product) {
            return null;
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $variant) {
            return null;
        }

        return [
            'product_id' => (int) $product->id,
            'product_variant_id' => (int) $variant->id,
        ];
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
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
