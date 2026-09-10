<?php

namespace App\Services\Products;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenAI;
use Smalot\PdfParser\Parser;

class PurchaseInvoiceAiService
{
    /**
     * @return array{
     *   supplier: ?string,
     *   invoice_number: ?string,
     *   products: array<int, array<string, mixed>>,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public function extract(Shop $shop, string $absolutePdfPath, User $user): array
    {
        $text = $this->extractPdfText($absolutePdfPath);
        if (Str::length(trim($text)) < 20) {
            throw ValidationException::withMessages([
                'file' => __('This PDF has little or no readable text. Upload a digital (not scanned) purchase invoice.'),
            ]);
        }

        $catalog = Product::query()
            ->where('shop_id', $shop->id)
            ->orderBy('name')
            ->limit(250)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $parsed = $this->parseInvoiceWithAi($text, $catalog, $user);
        $parsed['products'] = $this->groupProductsWithVariants($parsed['products']);
        if ($parsed['products'] === []) {
            throw ValidationException::withMessages([
                'file' => __('AI could not find product lines on this invoice. Try another PDF or add products manually.'),
            ]);
        }

        $products = [];
        foreach ($parsed['products'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $variants = [];
            foreach ($row['variants'] ?? [] as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $variantName = $this->nullableString($variant['name'] ?? null)
                    ?? $this->nullableString($variant['spec'] ?? null);
                $cost = $this->toMoney($variant['cost_price'] ?? 0);
                $qty = max(1, (int) ($variant['quantity'] ?? 1));
                $attributes = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];
                $variants[] = [
                    'name' => $variantName,
                    'quantity' => $qty,
                    'unit' => $this->nullableString($variant['unit'] ?? null) ?? 'pcs',
                    'cost_price' => $cost,
                    'sku' => $this->nullableString($variant['sku'] ?? null),
                    'attributes' => $this->normalizeAttributes($attributes),
                ];
            }

            if ($variants === []) {
                $variants[] = [
                    'name' => null,
                    'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                    'unit' => $this->nullableString($row['unit'] ?? null) ?? 'pcs',
                    'cost_price' => $this->toMoney($row['cost_price'] ?? 0),
                    'sku' => $this->nullableString($row['sku'] ?? null),
                    'attributes' => [],
                ];
            }

            $match = $this->findDuplicate($shop->id, $name, $row['matched_existing_name'] ?? null);

            $products[] = [
                'name' => Str::limit($name, 255, ''),
                'brand' => $this->nullableString($row['brand'] ?? null),
                'variants' => $variants,
                'duplicate' => $match !== null,
                'duplicate_match' => $match['match'] ?? null,
                'duplicate_product_id' => $match['product_id'] ?? null,
                'duplicate_product_name' => $match['product_name'] ?? null,
                'include' => $match === null,
            ];
        }

        if ($products === []) {
            throw ValidationException::withMessages([
                'file' => __('No usable product lines were found on this invoice.'),
            ]);
        }

        return [
            'supplier' => $this->nullableString($parsed['supplier'] ?? null),
            'invoice_number' => $this->nullableString($parsed['invoice_number'] ?? null),
            'products' => $products,
            'items' => $products,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{created: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function bulkCreate(
        Shop $shop,
        User $user,
        int $categoryId,
        string $status,
        array $items,
    ): array {
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($shop, $user, $categoryId, $status, $items, &$created, &$skipped) {
            foreach ($items as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $skipped[] = ['name' => '', 'reason' => 'empty_name'];
                    continue;
                }

                if (! empty($row['skip_if_duplicate'])) {
                    $match = $this->findDuplicate($shop->id, $name, null);
                    if ($match) {
                        $skipped[] = [
                            'name' => $name,
                            'reason' => 'duplicate',
                            'duplicate_product_id' => $match['product_id'],
                            'duplicate_product_name' => $match['product_name'],
                        ];
                        continue;
                    }
                }

                $brandId = $this->resolveBrandId($row['brand'] ?? null, $user);
                $slug = $this->uniqueProductSlug(Str::slug($name) ?: 'product');

                $incomingVariants = $row['variants'] ?? [];
                if (! is_array($incomingVariants) || $incomingVariants === []) {
                    $incomingVariants = [[
                        'name' => null,
                        'quantity' => $row['quantity'] ?? 0,
                        'selling_price' => $row['selling_price'] ?? 0,
                        'sku' => $row['sku'] ?? null,
                        'attributes' => [],
                    ]];
                }

                $product = Product::create([
                    'shop_id' => $shop->id,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'name' => Str::limit($name, 255, ''),
                    'slug' => $slug,
                    'description' => null,
                    'status' => $status,
                ]);

                $createdVariants = [];
                foreach ($incomingVariants as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }
                    $variantName = $this->nullableString($variant['name'] ?? null);
                    $sku = $this->uniqueSku(
                        $shop->id,
                        $variant['sku'] ?? null,
                        trim($name.' '.($variantName ?? ''))
                    );
                    $qty = max(0, (int) ($variant['quantity'] ?? 0));
                    $price = $this->toMoney($variant['selling_price'] ?? $variant['price'] ?? 0);
                    $attributes = $this->normalizeAttributes($variant['attributes'] ?? []);
                    $unit = $this->nullableString($variant['unit'] ?? null);
                    if ($unit !== null && ! isset($attributes['unit'])) {
                        $attributes['unit'] = $unit;
                    }

                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $sku,
                        'name' => $variantName,
                        'stock' => $qty,
                        'price' => $price,
                        'compare_at_price' => null,
                        'attributes' => $attributes,
                        'is_active' => true,
                    ]);

                    $createdVariants[] = [
                        'sku' => $sku,
                        'name' => $variantName,
                        'stock' => $qty,
                        'price' => $price,
                    ];
                }

                if ($createdVariants === []) {
                    $product->delete();
                    $skipped[] = ['name' => $name, 'reason' => 'no_variants'];
                    continue;
                }

                $created[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'variants' => $createdVariants,
                ];
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function extractPdfText(string $absolutePdfPath): string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePdfPath);
            $text = trim((string) $pdf->getText());
        } catch (\Throwable $e) {
            Log::warning('Purchase invoice PDF parse failed', [
                'message' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'file' => __('Could not read this PDF. Please upload a valid purchase invoice PDF.'),
            ]);
        }

        $text = $this->sanitizeText($text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return Str::limit($text, 14000, "\n...");
    }

    /**
     * @param  array<int, string>  $catalogNames
     * @return array{supplier:?string, invoice_number:?string, products: array<int, array<string, mixed>>}
     */
    protected function parseInvoiceWithAi(string $text, array $catalogNames, User $user): array
    {
        $catalogBlock = $catalogNames === []
            ? '(none)'
            : collect($catalogNames)->take(200)->implode("\n- ");

        $prompt = "CATALOG (existing products in this shop, for duplicate matching):\n- {$catalogBlock}\n\nINVOICE TEXT:\n{$text}";

        try {
            $raw = $this->askOpenAi($prompt);
        } catch (\Throwable $e) {
            Log::error('Purchase invoice AI extract failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'file' => __('Could not extract products from this invoice: :error', [
                    'error' => Str::limit($e->getMessage(), 220, ''),
                ]),
            ]);
        }

        $decoded = $this->decodeJson($raw);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'file' => __('AI returned an unexpected response. Please try again.'),
            ]);
        }

        $groups = $decoded['products'] ?? $decoded['items'] ?? [];
        if (! is_array($groups)) {
            $groups = [];
        }

        $rows = [];
        foreach ($groups as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }
            $variants = [];
            if (isset($row['variants']) && is_array($row['variants'])) {
                foreach ($row['variants'] as $variant) {
                    if (is_array($variant)) {
                        $variants[] = $variant;
                    }
                }
            }
            $row['variants'] = $variants;
            $rows[] = $row;
        }

        return [
            'supplier' => is_string($decoded['supplier'] ?? null) ? $decoded['supplier'] : null,
            'invoice_number' => is_string($decoded['invoice_number'] ?? null) ? $decoded['invoice_number'] : null,
            'products' => $rows,
        ];
    }

    /**
     * Collapse invoice rows into catalog products with size/spec variants.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function groupProductsWithVariants(array $rows): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productName = trim((string) ($row['name'] ?? ''));
            if ($productName === '') {
                continue;
            }

            $incoming = $row['variants'] ?? [];
            if (! is_array($incoming) || $incoming === []) {
                $incoming = [[
                    'name' => null,
                    'quantity' => $row['quantity'] ?? 1,
                    'unit' => $row['unit'] ?? null,
                    'cost_price' => $row['cost_price'] ?? 0,
                    'sku' => $row['sku'] ?? null,
                    'attributes' => $row['attributes'] ?? [],
                ]];
            }

            foreach ($incoming as $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $variantName = trim((string) ($variant['name'] ?? $variant['spec'] ?? ''));
                $lineDescription = $productName;
                if ($variantName !== '') {
                    if ($this->isSpecLike($variantName)) {
                        $lineDescription = trim($productName.' '.$variantName);
                    } elseif (mb_strlen($variantName) >= mb_strlen($productName)) {
                        $lineDescription = $variantName;
                    }
                }

                $family = $this->isSpecLike($variantName)
                    ? $this->familyName($productName)
                    : $this->familyName($lineDescription);

                if (mb_strlen($family) < 6) {
                    $family = $lineDescription;
                }

                $spec = $this->isSpecLike($variantName)
                    ? $this->tidySpec($variantName)
                    : $this->specFromName($lineDescription);

                $key = mb_strtolower($family);
                if (! isset($buckets[$key])) {
                    $buckets[$key] = [
                        'name' => $this->prettyProductName($family),
                        'brand' => $this->nullableString($row['brand'] ?? null),
                        'matched_existing_name' => $this->nullableString($row['matched_existing_name'] ?? null),
                        'variants' => [],
                    ];
                } elseif ($buckets[$key]['brand'] === null) {
                    $buckets[$key]['brand'] = $this->nullableString($row['brand'] ?? null);
                }

                $attributes = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];
                $attributes = array_merge($this->attributesFromName($lineDescription), $attributes);
                $unit = $this->nullableString($variant['unit'] ?? $row['unit'] ?? null);
                if ($unit !== null) {
                    $attributes['unit'] = $unit;
                }

                $buckets[$key]['variants'][] = [
                    'name' => $spec,
                    'quantity' => $variant['quantity'] ?? $row['quantity'] ?? 1,
                    'unit' => $unit,
                    'cost_price' => $variant['cost_price'] ?? $row['cost_price'] ?? 0,
                    'sku' => $variant['sku'] ?? $row['sku'] ?? null,
                    'attributes' => $attributes,
                ];
            }
        }

        return array_values($buckets);
    }

    protected function familyName(string $name): string
    {
        $s = $name;
        $s = preg_replace('/\(\s*\d+(?:\.\d+)?(?:\s*[xX×]\s*\d+(?:\.\d+)?)?\s*(mm|cm)\s*\)/i', ' ', $s) ?? $s;
        $s = preg_replace('/\(\s*\d+\s*\/\s*\d+\s*(?:"|”|inch|in)?\s*\)/i', ' ', $s) ?? $s;
        $s = preg_replace('/\(\s*\d+(?:\.\d+)?\s*(?:"|”|inch|in)\s*\)/i', ' ', $s) ?? $s;
        $s = preg_replace('/\b\d+(?:\.\d+)?(?:\s*[xX×]\s*\d+(?:\.\d+)?)?\s*(mm|cm)\b/i', ' ', $s) ?? $s;
        $s = preg_replace('/\b\d+\s*(?:"|”)?\s*[xX×]\s*\d+\s*\/\s*\d+\s*(?:"|”)?/i', ' ', $s) ?? $s;
        $s = preg_replace('/\b\d+\s*\/\s*\d+\s*(?:"|”|\'\'|inch\b|in\b)/i', ' ', $s) ?? $s;
        $s = preg_replace('/\b\d+(?:\.\d+)?\s*(?:"|”|\'\')/i', ' ', $s) ?? $s;
        $s = preg_replace('/\(\s*\)/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $family = trim($s, " \t-/,");

        return $family === '' ? trim($name) : $family;
    }

    protected function specFromName(string $name): ?string
    {
        $pattern = '/\d+(?:\.\d+)?\s*[xX×]\s*\d+(?:\.\d+)?\s*(?:mm|cm)|\d+(?:\.\d+)?\s*(?:mm|cm)|\d+\s*(?:"|”)?\s*[xX×]\s*\d+\s*\/\s*\d+\s*(?:"|”)?|\d+\s*\/\s*\d+\s*(?:"|”)|(?<![.\d\/])\d+\s*(?:"|”)/i';
        if (! preg_match_all($pattern, $name, $matches) || $matches[0] === []) {
            return null;
        }

        $tokens = array_map(fn ($token) => $this->tidySpec((string) $token), $matches[0]);
        $tokens = array_values(array_filter($tokens));
        if ($tokens === []) {
            return null;
        }

        $primary = array_shift($tokens);

        return $tokens === [] ? $primary : $primary.' ('.implode(', ', $tokens).')';
    }

    protected function tidySpec(?string $spec): ?string
    {
        $spec = trim((string) $spec);
        if ($spec === '') {
            return null;
        }

        $spec = preg_replace('/\s+/', ' ', $spec) ?? $spec;
        $spec = preg_replace('/\s*(mm|cm)\b/i', ' $1', $spec) ?? $spec;
        $spec = preg_replace_callback('/\b\d+(?:\.\d+)?(?:\s*[xX×]\s*\d+(?:\.\d+)?)?\s*mm\b/i', function ($m) {
            return strtoupper(preg_replace('/\s+/', '', str_ireplace('mm', 'MM', $m[0])) ?? $m[0]);
        }, $spec) ?? $spec;

        return trim($spec, " \t-/,");
    }

    protected function isSpecLike(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        if (preg_match('/\b(pipe|elbow|tee|mta|coupler|coupling|bend|valve|tank|wire|cable|switch|socket|union|adaptor|adapter|nipple|bush|flange|clamp|reducer|faucet|tap|pump|motor|sheet|board)\b/i', $name)) {
            return false;
        }

        return (bool) preg_match('/\d/', $name);
    }

    /**
     * @return array<string, string>
     */
    protected function attributesFromName(string $name): array
    {
        $attributes = [];
        if (preg_match('/(\d+(?:\.\d+)?(?:\s*[xX×]\s*\d+(?:\.\d+)?)?)\s*mm/i', $name, $m)) {
            $attributes['size'] = strtoupper(preg_replace('/\s+/', '', $m[1]).'MM');
        }
        if (preg_match('/(\d+\s*(?:"|”)?\s*[xX×]\s*\d+\s*\/\s*\d+\s*(?:"|”)?)/i', $name, $m)) {
            $attributes['inch'] = $this->tidySpec($m[1]) ?? trim($m[1]);
        } elseif (preg_match('/(\d+\s*\/\s*\d+|\d+)\s*(?:"|”)/', $name, $m)) {
            $attributes['inch'] = trim($m[1]).'"';
        }
        if (preg_match('/\bSDR\s*(\d+(?:\.\d+)?)/i', $name, $m)) {
            $attributes['sdr'] = $m[1];
        }

        return $attributes;
    }

    protected function prettyProductName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $titled = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
        $acronyms = ['CPVC', 'UPVC', 'PVC', 'SWR', 'SDR', 'MTA', 'MI', 'GI', 'PPR', 'HDPE', 'MS', 'SS'];
        foreach ($acronyms as $acronym) {
            $titled = preg_replace(
                '/\b'.preg_quote(mb_convert_case(mb_strtolower($acronym), MB_CASE_TITLE, 'UTF-8'), '/').'\b/u',
                $acronym,
                $titled
            ) ?? $titled;
        }

        return $titled;
    }

    /**
     * @return array{product_id:int, product_name:string, match:string}|null
     */
    public function findDuplicate(int $shopId, string $name, mixed $hintName = null): ?array
    {
        $hint = is_string($hintName) ? trim($hintName) : '';
        $candidates = array_values(array_filter([$hint, trim($name)]));

        foreach ($candidates as $term) {
            $exact = Product::query()
                ->where('shop_id', $shopId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($term)])
                ->orderBy('id')
                ->first();

            if ($exact) {
                return [
                    'product_id' => (int) $exact->id,
                    'product_name' => $exact->name,
                    'match' => 'exact',
                ];
            }
        }

        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        $products = Product::query()
            ->where('shop_id', $shopId)
            ->get(['id', 'name']);

        $best = null;
        $bestPercent = 0.0;
        foreach ($products as $product) {
            similar_text($normalized, mb_strtolower((string) $product->name), $percent);
            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $best = $product;
            }
        }

        if ($best && $bestPercent >= 86) {
            return [
                'product_id' => (int) $best->id,
                'product_name' => $best->name,
                'match' => 'similar',
            ];
        }

        $like = Product::query()
            ->where('shop_id', $shopId)
            ->whereRaw('LOWER(name) like ?', ['%'.$normalized.'%'])
            ->orderBy('name')
            ->first();

        if ($like) {
            return [
                'product_id' => (int) $like->id,
                'product_name' => $like->name,
                'match' => 'similar',
            ];
        }

        return null;
    }

    protected function resolveBrandId(mixed $brandName, User $user): ?int
    {
        $name = $this->nullableString($brandName);
        if ($name === null) {
            return null;
        }

        $existing = Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $baseSlug = Str::slug($name) ?: 'brand';
        $slug = $baseSlug;
        $i = 2;
        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $brand = Brand::create([
            'name' => $name,
            'slug' => $slug,
            'is_approved' => true,
            'created_by' => $user->id,
        ]);

        return (int) $brand->id;
    }

    protected function uniqueProductSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function uniqueSku(int $shopId, mixed $preferred, string $name): string
    {
        $preferred = $this->nullableString($preferred);
        $base = $preferred
            ?: ('INV-'.$shopId.'-'.Str::upper(Str::substr(Str::slug($name) ?: 'ITEM', 0, 18)).'-'.Str::upper(Str::random(4)));

        $sku = Str::limit($base, 240, '');
        $counter = 2;
        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = Str::limit($base, 230, '').'-'.$counter;
            $counter++;
        }

        return $sku;
    }

    protected function askOpenAi(string $prompt): string
    {
        $apiKey = (string) config('laragent.providers.default.api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not set.');
        }

        $client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new GuzzleClient([
                'timeout' => 120,
                'connect_timeout' => 20,
            ]))
            ->make();

        $result = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $raw = trim((string) ($result->choices[0]->message->content ?? ''));
        if ($raw === '') {
            throw new \RuntimeException('OpenAI returned an empty response.');
        }

        return $raw;
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract purchased goods from a supplier purchase invoice or sales order.

The layout varies by supplier. Ignore letterheads, GST/tax tables, bank details, and totals. Focus on line items (products/goods).

Return ONLY valid JSON. No markdown. No extra text.

Output format:
{
  "supplier": "optional supplier name or null",
  "invoice_number": "optional invoice/order no or null",
  "products": [
    {
      "name": "catalog product name WITHOUT size/spec",
      "brand": "brand if clearly present else null",
      "matched_existing_name": "exact catalog name if this is the same product else null",
      "variants": [
        {
          "name": "size / spec only, e.g. 20MM (3/4\") SDR 13.5",
          "quantity": 50,
          "unit": "PIPE",
          "cost_price": 403.0,
          "sku": "supplier sku/hsn/code if present else null",
          "attributes": { "size": "20MM", "inch": "3/4\"", "sdr": "13.5" }
        }
      ]
    }
  ]
}

Grouping rules:
- Do NOT create one product per invoice row.
- Same brand + same item type (pipe, elbow, tee, MTA, etc.) = ONE product with multiple variants.
- Put size, diameter, inch, length, SDR, color, pack size into the variant name and attributes.
- Product "name" is the shared title, e.g. "Supreme CPVC Pipe SDR 13.5", not "Supreme CPVC PIPE 20MM".
- Example: "SUPREME CPVC PIPE 20MM (3/4\") SDR 13.5" and "SUPREME CPVC PIPE 25MM (1\") SDR 13.5" are two variants of one product.
- Different fittings stay different products: pipe, elbow, tee, MTA, Y, coupler, etc.
- If a line has no size/spec, still include one variant with name null.
- "quantity" is purchased units (integer). If missing, use 1.
- "cost_price" is the unit list/purchase rate (not line total). Parse numbers like 1,250.00.
- Skip freight, packing, round-off, and tax-only rows.
- If a CATALOG section is provided, set matched_existing_name when the product (not a single size) already exists.
PROMPT;
    }

    protected function sanitizeText(string $text): string
    {
        $text = (string) (@iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;

        return trim($text);
    }

    protected function decodeJson(string $raw): mixed
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        $start = strpos($raw, '[');
        $end = strrpos($raw, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return ['items' => $decoded];
            }
        }

        return null;
    }

    protected function toMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round(max(0, (float) $value), 2);
        }

        $raw = trim((string) $value);
        $raw = str_replace([',', '₹', 'Rs.', 'Rs', 'INR'], '', $raw);
        $raw = preg_replace('/[^\d.\-]/', '', $raw) ?? $raw;

        return round(max(0, (float) $raw), 2);
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || strtolower($value) === 'null' ? null : Str::limit($value, 255, '');
    }

    /**
     * @param  mixed  $attributes
     * @return array<string, string>
     */
    protected function normalizeAttributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $clean = [];
        foreach ($attributes as $key => $value) {
            if (! is_string($key) && ! is_numeric($key)) {
                continue;
            }
            $label = trim((string) $key);
            $text = $this->nullableString($value);
            if ($label === '' || $text === null) {
                continue;
            }
            $clean[Str::limit($label, 40, '')] = Str::limit($text, 80, '');
        }

        return $clean;
    }
}
