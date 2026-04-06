<?php

namespace App\Services\Orders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderOnBehalfAiService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, missing: array<int, string>}
     */
    public function suggest(int $shopId, string $prompt, User $user): array
    {
        $prompt = trim($prompt);
        $requested = $this->parsePromptIntoItems($prompt, $user);
        if ($requested === []) {
            throw ValidationException::withMessages([
                'prompt' => __('Could not understand the text. Try: "2 Havells 5A MCB and 1 Finolex 1.5mm wire".'),
            ]);
        }

        $items = [];
        $missing = [];

        foreach ($requested as $req) {
            $term = (string) $req['term'];
            $qty = max(1, (int) $req['quantity']);
            $match = $this->findBestProductMatch($shopId, $term);

            if (! $match) {
                $missing[] = $term;
                continue;
            }

            $product = Product::with([
                'brand',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
            ])->find($match['product_id']);
            $variant = ProductVariant::find($match['product_variant_id']);
            if (! $product || ! $variant) {
                $missing[] = $term;
                continue;
            }

            $items[] = [
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'brand' => $product->brand?->name,
                'quantity' => $qty,
                'variant_id' => (int) $variant->id,
                'variant_label' => $variant->name ?: $variant->sku ?: '#'.$variant->id,
                'price' => (float) $variant->price,
                'stock' => (int) $variant->stock,
                'variants' => $product->variants->map(fn (ProductVariant $v) => [
                    'id' => (int) $v->id,
                    'label' => $v->name ?: $v->sku ?: '#'.$v->id,
                    'price' => (float) $v->price,
                    'stock' => (int) $v->stock,
                ])->values(),
            ];
        }

        return [
            'data' => $items,
            'missing' => $missing,
        ];
    }

    /**
     * @return array<int, array{quantity:int, term:string}>
     */
    public function parsePromptIntoItems(string $prompt, User $user): array
    {
        if (class_exists(\LarAgent\Agent::class) && class_exists(\App\AiAgents\OrderItemsParserAgent::class)) {
            try {
                /** @var string $raw */
                $raw = \App\AiAgents\OrderItemsParserAgent::forUser($user)->respond($prompt);
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
            } catch (\Throwable) {
                // Fall through to regex parser.
            }
        }

        $normalized = preg_replace('/\s+/', ' ', trim($prompt)) ?? $prompt;
        $normalized = preg_replace('/^add\s+/i', '', $normalized) ?? $normalized;

        $parts = preg_split('/\s*(?:,|&|\band\b|\+)\s*/i', $normalized) ?: [];
        $items = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

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
    public function findBestProductMatch(int $shopId, string $term): ?array
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
            $tokens = collect(preg_split('/\s+/', strtolower($term)) ?: [])
                ->map(fn ($t) => trim($t))
                ->filter(fn ($t) => $t !== '' && Str::length($t) >= 3)
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
}
