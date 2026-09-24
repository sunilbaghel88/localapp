<?php

namespace App\Services\Orders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderOnBehalfAiService
{
    /**
     * @return array{
     *   data: array<int, array<string, mixed>>,
     *   missing: array<int, string>
     * }
     */
    public function suggest(int $shopId, string $prompt, User $user): array
    {
        $prompt = trim($prompt);
        $requested = $this->parsePromptIntoItems($prompt, $user);
        if ($requested === []) {
            throw ValidationException::withMessages([
                'prompt' => __('Could not understand the text. Try: "2 Havells 32A MCB and 1 Finolex 1.5mm wire".'),
            ]);
        }

        $catalog = $this->loadCatalog($shopId);
        $items = [];
        $missing = [];

        foreach ($requested as $req) {
            $term = (string) $req['term'];
            $qty = max(1, (int) $req['quantity']);
            $ranked = $this->rankMatches($catalog, $term);

            if ($ranked === []) {
                $missing[] = $term;
                continue;
            }

            $best = $ranked[0];
            $second = $ranked[1] ?? null;
            $needsPick = $second !== null
                && (int) $second['score'] >= (int) $best['score'] * 0.85
                && (int) $second['score'] >= 20;

            $items[] = $this->formatMatch($best['product'], $best['variant'], $qty, $ranked, $needsPick);
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
                    $rows = collect($decoded)
                        ->filter(fn ($row) => is_array($row) && isset($row['term']))
                        ->map(fn ($row) => [
                            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                            'term' => trim((string) ($row['term'] ?? '')),
                        ])
                        ->filter(fn ($row) => $row['term'] !== '')
                        ->values()
                        ->all();
                    if ($rows !== []) {
                        return $rows;
                    }
                }
            } catch (\Throwable) {
                // Fall through to regex parser.
            }
        }

        return $this->parsePromptFallback($prompt);
    }

    /**
     * @return array<int, array{quantity:int, term:string}>
     */
    public function parsePromptFallback(string $prompt): array
    {
        $normalized = $this->replaceSpokenQuantities($prompt);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;
        $normalized = preg_replace('/^(add|please\s+add|i\s+want|i\s+need)\s+/i', '', $normalized) ?? $normalized;

        $parts = preg_split('/\s*(?:,|&|\band\b|\bplus\b|\+|\;)\s*/i', $normalized) ?: [];
        $items = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(?<qty>\d+)\s+(?:x|times|pcs|pc|nos|no\.?|pieces?)?\s*(?<term>.+)$/iu', $part, $m)) {
                $items[] = [
                    'quantity' => max(1, (int) $m['qty']),
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
        $ranked = $this->rankMatches($this->loadCatalog($shopId), $term);
        if ($ranked === []) {
            return null;
        }

        return [
            'product_id' => (int) $ranked[0]['product']->id,
            'product_variant_id' => (int) $ranked[0]['variant']->id,
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    protected function loadCatalog(int $shopId): Collection
    {
        return Product::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'archived')
            ->with([
                'brand',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
            ])
            ->limit(800)
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $catalog
     * @return array<int, array{product:Product, variant:ProductVariant, score:int}>
     */
    protected function rankMatches(Collection $catalog, string $term): array
    {
        $term = trim($term);
        if ($term === '' || $catalog->isEmpty()) {
            return [];
        }

        $normalized = $this->normalizeSearchText($term);
        $tokens = $this->searchTokens($normalized);
        $specs = $this->extractSpecs($normalized);
        if ($tokens === [] && $specs === []) {
            return [];
        }

        $ranked = [];
        foreach ($catalog as $product) {
            $variants = $product->variants;
            if ($variants->isEmpty()) {
                continue;
            }

            $productHaystack = $this->normalizeSearchText(trim(
                $product->name.' '.($product->brand?->name ?? '')
            ));

            foreach ($variants as $variant) {
                $variantHaystack = $this->normalizeSearchText(trim(implode(' ', array_filter([
                    $variant->name,
                    $variant->sku,
                    ...array_map('strval', array_values($variant->attributes ?? [])),
                ]))));
                $haystack = trim($productHaystack.' '.$variantHaystack);
                $score = $this->scoreHaystack($haystack, $productHaystack, $variantHaystack, $tokens, $specs);
                if ($score < 16) {
                    continue;
                }
                $ranked[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'score' => $score,
                ];
            }
        }

        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($ranked, 0, 8);
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, string>  $specs
     */
    protected function scoreHaystack(
        string $haystack,
        string $productHaystack,
        string $variantHaystack,
        array $tokens,
        array $specs,
    ): int {
        $score = 0;

        foreach ($tokens as $token) {
            $weight = Str::length($token) >= 5 ? 14 : 9;
            if (str_contains($productHaystack, $token)) {
                $score += $weight + 4;
            } elseif (str_contains($haystack, $token)) {
                $score += $weight;
            }
        }

        foreach ($specs as $spec) {
            $number = preg_replace('/[a-z]+$/', '', $spec) ?? $spec;
            $unit = preg_replace('/^[0-9.]+/', '', $spec) ?? '';
            if ($spec !== '' && str_contains($variantHaystack, $spec)) {
                $score += 48;
            } elseif ($spec !== '' && str_contains($haystack, $spec)) {
                $score += 36;
            } elseif ($number !== '' && $unit !== '' && preg_match('/\b'.preg_quote($number, '/').'\s*'.preg_quote($unit, '/').'\b/', $haystack)) {
                $score += 40;
            } elseif ($number !== '' && preg_match('/\b'.preg_quote($number, '/').'(a|amp|mm|inch)?\b/', $variantHaystack)) {
                $score += 28;
            }
        }

        return $score;
    }

    /**
     * @param  array<int, array{product:Product, variant:ProductVariant, score:int}>  $ranked
     * @return array<string, mixed>
     */
    protected function formatMatch(
        Product $product,
        ProductVariant $variant,
        int $qty,
        array $ranked,
        bool $needsPick,
    ): array {
        $payload = $this->presentProduct($product, $variant, $qty);
        $payload['needs_pick'] = $needsPick;
        $payload['confidence'] = $needsPick ? 'medium' : 'high';
        $payload['candidates'] = [];

        if ($needsPick) {
            $seen = [];
            foreach ($ranked as $row) {
                $key = $row['product']->id.':'.$row['variant']->id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $payload['candidates'][] = $this->presentProduct($row['product'], $row['variant'], $qty);
                if (count($payload['candidates']) >= 5) {
                    break;
                }
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentProduct(Product $product, ProductVariant $variant, int $qty): array
    {
        $product->loadMissing('brand');

        return [
            'product_id' => (int) $product->id,
            'product_name' => $product->name,
            'product_name_hi' => $product->name_hi,
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
            ])->values()->all(),
        ];
    }

    protected function normalizeSearchText(string $text): string
    {
        $t = mb_strtolower($text);
        $t = str_replace(['"', '”', '“', "'"], ' inch ', $t);
        $t = preg_replace('/\b(\d+(?:\.\d+)?)\s*(ampere|amperes|amps|amp|a)\b/u', '$1a', $t) ?? $t;
        $t = preg_replace('/\b(\d+(?:\.\d+)?)\s*(millimetres?|millimeters?|mm)\b/u', '$1mm', $t) ?? $t;
        $t = preg_replace('/\b(\d+(?:\.\d+)?)\s*(inches|inch|in)\b/u', '$1inch', $t) ?? $t;
        $t = preg_replace('/\bminiature\s+circuit\s+breakers?\b/u', 'mcb', $t) ?? $t;
        $t = preg_replace('/\bresidual\s+current\s+circuit\s+breakers?\b/u', 'rccb', $t) ?? $t;
        $t = preg_replace('/[^a-z0-9.]+/u', ' ', $t) ?? $t;

        return trim(preg_replace('/\s+/', ' ', $t) ?? $t);
    }

    /**
     * @return array<int, string>
     */
    protected function searchTokens(string $normalized): array
    {
        $stop = [
            'a', 'an', 'the', 'of', 'for', 'and', 'with', 'piece', 'pieces', 'pcs', 'pc',
            'nos', 'no', 'item', 'items', 'please', 'add', 'want', 'need',
        ];

        return collect(preg_split('/\s+/', $normalized) ?: [])
            ->map(fn ($t) => trim((string) $t))
            ->filter(function ($t) use ($stop) {
                if ($t === '' || in_array($t, $stop, true)) {
                    return false;
                }
                if (preg_match('/^\d/', $t)) {
                    return false;
                }

                return Str::length($t) >= 3;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function extractSpecs(string $normalized): array
    {
        $specs = [];
        if (preg_match_all('/\b(\d+(?:\.\d+)?)(a|mm|inch|kg|mtr|sqmm|swg)?\b/u', $normalized, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $specs[] = $match[1].($match[2] ?? '');
            }
        }

        return array_values(array_unique($specs));
    }

    protected function replaceSpokenQuantities(string $prompt): string
    {
        $map = [
            'dozen' => '12',
            'ek' => '1',
            'one' => '1',
            'do' => '2',
            'two' => '2',
            'teen' => '3',
            'three' => '3',
            'char' => '4',
            'four' => '4',
            'panch' => '5',
            'paanch' => '5',
            'five' => '5',
            'chhe' => '6',
            'chhah' => '6',
            'six' => '6',
            'saat' => '7',
            'seven' => '7',
            'aath' => '8',
            'eight' => '8',
            'nau' => '9',
            'nine' => '9',
            'das' => '10',
            'ten' => '10',
        ];

        $normalized = preg_replace('/\s+/', ' ', trim($prompt)) ?? $prompt;

        foreach ($map as $word => $number) {
            $normalized = preg_replace('/\b'.preg_quote($word, '/').'\b/iu', $number, $normalized) ?? $normalized;
        }

        return $normalized;
    }
}
