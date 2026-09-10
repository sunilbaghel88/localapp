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
        if ($parsed['items'] === []) {
            throw ValidationException::withMessages([
                'file' => __('AI could not find product lines on this invoice. Try another PDF or add products manually.'),
            ]);
        }

        $items = [];
        foreach ($parsed['items'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $cost = $this->toMoney($row['cost_price'] ?? 0);
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $match = $this->findDuplicate($shop->id, $name, $row['matched_existing_name'] ?? null);

            $items[] = [
                'name' => Str::limit($name, 255, ''),
                'brand' => $this->nullableString($row['brand'] ?? null),
                'quantity' => $qty,
                'unit' => $this->nullableString($row['unit'] ?? null) ?? 'pcs',
                'cost_price' => $cost,
                'sku' => $this->nullableString($row['sku'] ?? null),
                'duplicate' => $match !== null,
                'duplicate_match' => $match['match'] ?? null,
                'duplicate_product_id' => $match['product_id'] ?? null,
                'duplicate_product_name' => $match['product_name'] ?? null,
                'include' => $match === null,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'file' => __('No usable product lines were found on this invoice.'),
            ]);
        }

        return [
            'supplier' => $this->nullableString($parsed['supplier'] ?? null),
            'invoice_number' => $this->nullableString($parsed['invoice_number'] ?? null),
            'items' => $items,
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
                $sku = $this->uniqueSku($shop->id, $row['sku'] ?? null, $name);
                $qty = max(0, (int) ($row['quantity'] ?? 0));
                $price = $this->toMoney($row['selling_price'] ?? 0);

                $product = Product::create([
                    'shop_id' => $shop->id,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'name' => Str::limit($name, 255, ''),
                    'slug' => $slug,
                    'description' => null,
                    'status' => $status,
                ]);

                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'name' => null,
                    'stock' => $qty,
                    'price' => $price,
                    'compare_at_price' => null,
                    'attributes' => [],
                    'is_active' => true,
                ]);

                $created[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $sku,
                    'stock' => $qty,
                    'price' => $price,
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
     * @return array{supplier:?string, invoice_number:?string, items: array<int, array<string, mixed>>}
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

        $items = $decoded['items'] ?? $decoded;
        if (! is_array($items)) {
            $items = [];
        }

        $rows = [];
        foreach ($items as $row) {
            if (is_array($row) && ! empty($row['name'])) {
                $rows[] = $row;
            }
        }

        return [
            'supplier' => is_string($decoded['supplier'] ?? null) ? $decoded['supplier'] : null,
            'invoice_number' => is_string($decoded['invoice_number'] ?? null) ? $decoded['invoice_number'] : null,
            'items' => $rows,
        ];
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
  "items": [
    {
      "name": "clean product name for a shop catalog",
      "brand": "brand if clearly present else null",
      "quantity": 10,
      "unit": "pcs",
      "cost_price": 125.5,
      "sku": "supplier sku/hsn/code if present else null",
      "matched_existing_name": "exact catalog name if this is the same product else null"
    }
  ]
}

Rules:
- "name" must be a sellable product title (brand + item + key specs). Drop invoice-only noise (HSN columns, tax %, amounts as names).
- "quantity" is purchased units (integer). If missing, use 1.
- "cost_price" is the unit purchase/rate from the invoice (not line total, not selling price). Parse numbers like 1,250.00.
- Merge obvious duplicate lines of the same product by summing quantity.
- If a CATALOG section is provided, set matched_existing_name when the invoice line is clearly the same product (even if wording differs slightly). Otherwise null.
- Skip freight, packing, round-off, and tax-only rows.
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
}
