<?php

namespace App\Services\Products;

use App\AiAgents\PurchaseInvoiceParserAgent;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $parsed = $this->parseInvoiceWithAi($text, $user);
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
                $variants[] = $this->normalizeExtractedLine($variant, $row);
            }

            if ($variants === []) {
                $variants[] = $this->normalizeExtractedLine($row, $row);
            }

            $descriptions = [];
            foreach ($variants as $variant) {
                $description = $variant['goods_description'] ?? null;
                if (is_string($description) && trim($description) !== '') {
                    $descriptions[] = $description;
                }
            }
            $match = $this->findDuplicateByGoodsDescription($shop->id, $descriptions);
            $hsn = $this->nullableString($row['hsn_code'] ?? null);
            if ($hsn === null) {
                foreach ($variants as $variant) {
                    if (! empty($variant['hsn_code'])) {
                        $hsn = $variant['hsn_code'];
                        break;
                    }
                }
            }

            $products[] = [
                'name' => Str::limit($name, 255, ''),
                'brand' => $this->nullableString($row['brand'] ?? null),
                'hsn_code' => $hsn,
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

        $header = $this->reconcileInvoiceTax($this->normalizeInvoiceHeader($parsed, $products), $products);

        return [
            'supplier' => $header['supplier_name'],
            'supplier_name' => $header['supplier_name'],
            'supplier_gstin' => $header['supplier_gstin'],
            'invoice_number' => $header['invoice_number'],
            'invoice_date' => $header['invoice_date'],
            'cgst_amount' => $header['cgst_amount'],
            'sgst_amount' => $header['sgst_amount'],
            'igst_amount' => $header['igst_amount'],
            'products' => $products,
            'items' => $products,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $invoiceMeta
     * @return array{
     *   created: array<int, array<string, mixed>>,
     *   skipped: array<int, array<string, mixed>>,
     *   purchase_invoice: ?array<string, mixed>
     * }
     */
    public function bulkCreate(
        Shop $shop,
        User $user,
        int $categoryId,
        string $status,
        array $items,
        array $invoiceMeta = [],
    ): array {
        $created = [];
        $skipped = [];
        $invoice = null;
        $invoiceItems = [];

        DB::transaction(function () use ($shop, $user, $categoryId, $status, $items, $invoiceMeta, &$created, &$skipped, &$invoice, &$invoiceItems) {
            foreach ($items as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $skipped[] = ['name' => '', 'reason' => 'empty_name'];
                    continue;
                }

                if (! empty($row['skip_if_duplicate'])) {
                    $descriptions = [];
                    foreach ($row['variants'] ?? [] as $candidate) {
                        if (is_array($candidate)) {
                            $description = $candidate['goods_description'] ?? null;
                            if (is_string($description) && trim($description) !== '') {
                                $descriptions[] = $description;
                            }
                        }
                    }
                    $match = $this->findDuplicateByGoodsDescription($shop->id, $descriptions);
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
                $productHsn = $this->normalizeHsn($row['hsn_code'] ?? null);

                $incomingVariants = $row['variants'] ?? [];
                if (! is_array($incomingVariants) || $incomingVariants === []) {
                    $incomingVariants = [[
                        'name' => null,
                        'goods_description' => $row['goods_description'] ?? null,
                        'quantity' => $row['quantity'] ?? 0,
                        'selling_price' => $row['selling_price'] ?? 0,
                        'sku' => $row['sku'] ?? null,
                        'hsn_code' => $row['hsn_code'] ?? null,
                        'list_price' => $row['list_price'] ?? null,
                        'discount_percent' => $row['discount_percent'] ?? null,
                        'cost_price' => $row['cost_price'] ?? 0,
                        'cgst_amount' => $row['cgst_amount'] ?? 0,
                        'sgst_amount' => $row['sgst_amount'] ?? 0,
                        'igst_amount' => $row['igst_amount'] ?? 0,
                        'attributes' => [],
                    ]];
                }

                if ($productHsn === null) {
                    foreach ($incomingVariants as $candidate) {
                        if (is_array($candidate)) {
                            $productHsn = $this->normalizeHsn($candidate['hsn_code'] ?? null);
                            if ($productHsn !== null) {
                                break;
                            }
                        }
                    }
                }

                $product = Product::create([
                    'shop_id' => $shop->id,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'name' => Str::limit($name, 255, ''),
                    'slug' => $slug,
                    'description' => null,
                    'status' => $status,
                    'hsn_code' => $productHsn,
                ]);

                $createdVariants = [];
                foreach ($incomingVariants as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }
                    $pricing = $this->resolveLinePricing($variant, $row);
                    $variantName = $this->nullableString($variant['name'] ?? null);
                    $goodsDescription = $this->nullableString($variant['goods_description'] ?? $row['goods_description'] ?? null, 2000);
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

                    $createdVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $sku,
                        'name' => $variantName,
                        'goods_description' => $goodsDescription,
                        'stock' => $qty,
                        'price' => $price,
                        'cost_price' => $pricing['cost_price'],
                        'compare_at_price' => null,
                        'attributes' => $attributes,
                        'is_active' => true,
                    ]);

                    $createdVariants[] = [
                        'id' => $createdVariant->id,
                        'sku' => $sku,
                        'name' => $variantName,
                        'stock' => $qty,
                        'price' => $price,
                        'cost_price' => $pricing['cost_price'],
                    ];

                    $invoiceItems[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $createdVariant->id,
                        'name' => $goodsDescription ?? $product->name,
                        'variant_name' => $variantName,
                        'hsn_code' => $pricing['hsn_code'] ?? $productHsn,
                        'unit' => $unit,
                        'quantity' => $qty,
                        'list_price' => $pricing['list_price'],
                        'discount_percent' => $pricing['discount_percent'],
                        'cost_price' => $pricing['cost_price'],
                        'selling_price' => $price,
                        'cgst_amount' => $pricing['cgst_amount'],
                        'sgst_amount' => $pricing['sgst_amount'],
                        'igst_amount' => $pricing['igst_amount'],
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
                    'hsn_code' => $product->hsn_code,
                    'variants' => $createdVariants,
                ];
            }

            if ($invoiceItems !== []) {
                $header = $this->normalizeInvoiceHeader($invoiceMeta, []);
                if ($header['cgst_amount'] <= 0 && $header['sgst_amount'] <= 0 && $header['igst_amount'] <= 0) {
                    $header['cgst_amount'] = $this->sumMoney(array_column($invoiceItems, 'cgst_amount'));
                    $header['sgst_amount'] = $this->sumMoney(array_column($invoiceItems, 'sgst_amount'));
                    $header['igst_amount'] = $this->sumMoney(array_column($invoiceItems, 'igst_amount'));
                }

                $invoice = PurchaseInvoice::create([
                    'shop_id' => $shop->id,
                    'created_by' => $user->id,
                    'supplier_name' => $header['supplier_name'],
                    'supplier_gstin' => $header['supplier_gstin'],
                    'invoice_number' => $header['invoice_number'],
                    'invoice_date' => $header['invoice_date'],
                    'cgst_amount' => $header['cgst_amount'],
                    'sgst_amount' => $header['sgst_amount'],
                    'igst_amount' => $header['igst_amount'],
                    'source_filename' => $this->nullableString($invoiceMeta['source_filename'] ?? null),
                    'status' => 'imported',
                ]);

                foreach ($invoiceItems as $item) {
                    PurchaseInvoiceItem::create([
                        'purchase_invoice_id' => $invoice->id,
                        ...$item,
                    ]);
                }

                $invoice->load(['items.product', 'items.variant', 'shop']);
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'purchase_invoice' => $invoice?->toArray(),
        ];
    }

    public function extractPdfText(string $absolutePdfPath): string
    {
        $blocks = [];
        foreach ($this->extractPdfPages($absolutePdfPath) as $index => $page) {
            $blocks[] = '--- PAGE '.($index + 1)." ---\n".$page;
        }

        return trim(implode("\n\n", $blocks));
    }

    /**
     * @return array<int, string>
     */
    public function extractPdfPages(string $absolutePdfPath): array
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePdfPath);
            $pages = [];
            foreach ($pdf->getPages() as $page) {
                if ($page === null) {
                    continue;
                }
                $text = $this->cleanPdfText((string) $page->getText());
                if ($text !== '') {
                    $pages[] = $text;
                }
            }
            if ($pages === []) {
                $fallback = $this->cleanPdfText((string) $pdf->getText());
                if ($fallback !== '') {
                    $pages[] = $fallback;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Purchase invoice PDF parse failed', [
                'message' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'file' => __('Could not read this PDF. Please upload a valid purchase invoice PDF.'),
            ]);
        }

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseInvoiceWithAi(string $text, User $user): array
    {
        try {
            $raw = PurchaseInvoiceParserAgent::forUser($user)->respond("INVOICE TEXT:\n{$text}");
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

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = $this->decodeJson(trim((string) $raw));
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'file' => __('AI returned an unexpected response. Please try again.'),
            ]);
        }

        $supplier = $decoded['supplier'] ?? $decoded['supplier_name'] ?? null;
        $supplierName = $decoded['supplier_name'] ?? $decoded['supplier'] ?? null;

        return [
            'supplier' => is_string($supplier) ? $supplier : null,
            'supplier_name' => is_string($supplierName) ? $supplierName : null,
            'supplier_gstin' => $decoded['supplier_gstin'] ?? $decoded['gstin'] ?? $decoded['gst_no'] ?? null,
            'invoice_number' => is_string($decoded['invoice_number'] ?? null) ? $decoded['invoice_number'] : null,
            'invoice_date' => $decoded['invoice_date'] ?? null,
            'cgst_amount' => $decoded['cgst_amount'] ?? $decoded['cgst'] ?? 0,
            'sgst_amount' => $decoded['sgst_amount'] ?? $decoded['sgst'] ?? 0,
            'igst_amount' => $decoded['igst_amount'] ?? $decoded['igst'] ?? 0,
            'products' => $this->rowsFromDecoded($decoded),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rowsFromDecoded(array $decoded): array
    {
        $lines = $decoded['lines'] ?? null;
        if (is_array($lines)) {
            $rows = [];
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $goods = trim((string) ($line['goods_description'] ?? $line['description_of_goods'] ?? ''));
                $name = trim((string) ($line['name'] ?? ''));
                if ($goods === '') {
                    $goods = $name;
                }
                if ($name === '') {
                    $name = $goods;
                }
                if ($name === '') {
                    continue;
                }
                $rows[] = [
                    'name' => $name,
                    'goods_description' => $goods,
                    'brand' => $line['brand'] ?? null,
                    'hsn_code' => $line['hsn_code'] ?? $line['hsn'] ?? null,
                    'variants' => [[
                        'name' => null,
                        'goods_description' => $goods,
                        'quantity' => $line['quantity'] ?? 1,
                        'unit' => $line['unit'] ?? null,
                        'hsn_code' => $line['hsn_code'] ?? $line['hsn'] ?? null,
                        'list_price' => $line['list_price'] ?? $line['rate'] ?? null,
                        'discount_percent' => $line['discount_percent'] ?? $line['discount'] ?? null,
                        'cost_price' => $line['cost_price'] ?? null,
                        'cgst_amount' => $line['cgst_amount'] ?? $line['cgst'] ?? 0,
                        'sgst_amount' => $line['sgst_amount'] ?? $line['sgst'] ?? 0,
                        'igst_amount' => $line['igst_amount'] ?? $line['igst'] ?? 0,
                        'sku' => $line['sku'] ?? null,
                        'attributes' => is_array($line['attributes'] ?? null) ? $line['attributes'] : [],
                    ]],
                ];
            }

            return $rows;
        }

        $groups = $decoded['products'] ?? $decoded['items'] ?? [];
        if (! is_array($groups)) {
            return [];
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

        return $rows;
    }

    /**
     * @param  array{supplier_name:?string, supplier_gstin:?string, invoice_number:?string, invoice_date:?string, cgst_amount:float, sgst_amount:float, igst_amount:float}  $header
     * @param  array<int, array<string, mixed>>  $products
     * @return array{supplier_name:?string, supplier_gstin:?string, invoice_number:?string, invoice_date:?string, cgst_amount:float, sgst_amount:float, igst_amount:float}
     */
    protected function reconcileInvoiceTax(array $header, array $products): array
    {
        $cgst = 0.0;
        $sgst = 0.0;
        $igst = 0.0;
        foreach ($products as $product) {
            foreach ($product['variants'] ?? [] as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $cgst += $this->toMoney($variant['cgst_amount'] ?? 0);
                $sgst += $this->toMoney($variant['sgst_amount'] ?? 0);
                $igst += $this->toMoney($variant['igst_amount'] ?? 0);
            }
        }

        $header['cgst_amount'] = max($header['cgst_amount'], round($cgst, 2));
        $header['sgst_amount'] = max($header['sgst_amount'], round($sgst, 2));
        $header['igst_amount'] = max($header['igst_amount'], round($igst, 2));

        return $header;
    }

    protected function cleanPdfText(string $text): string
    {
        $text = $this->sanitizeText($text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
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
                    'goods_description' => $row['goods_description'] ?? null,
                    'quantity' => $row['quantity'] ?? 1,
                    'unit' => $row['unit'] ?? null,
                    'cost_price' => $row['cost_price'] ?? 0,
                    'list_price' => $row['list_price'] ?? null,
                    'discount_percent' => $row['discount_percent'] ?? $row['discount'] ?? null,
                    'hsn_code' => $row['hsn_code'] ?? $row['hsn'] ?? null,
                    'cgst_amount' => $row['cgst_amount'] ?? $row['cgst'] ?? 0,
                    'sgst_amount' => $row['sgst_amount'] ?? $row['sgst'] ?? 0,
                    'igst_amount' => $row['igst_amount'] ?? $row['igst'] ?? 0,
                    'sku' => $row['sku'] ?? null,
                    'attributes' => $row['attributes'] ?? [],
                ]];
            }

            foreach ($incoming as $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $variantName = trim((string) ($variant['name'] ?? $variant['spec'] ?? ''));
                $goods = trim((string) ($variant['goods_description'] ?? $row['goods_description'] ?? ''));
                $lineDescription = $goods !== '' ? $goods : $productName;
                if ($goods === '' && $variantName !== '') {
                    if ($this->isSpecLike($variantName)) {
                        $lineDescription = trim($productName.' '.$variantName);
                    } elseif (mb_strlen($variantName) >= mb_strlen($productName)) {
                        $lineDescription = $variantName;
                    }
                }

                $nameIsRaw = $goods !== '' && $this->normalizeGoodsKey($productName) === $this->normalizeGoodsKey($goods);
                $catalogName = $nameIsRaw || trim($productName) === ''
                    ? $this->prettyProductName($this->familyName($lineDescription))
                    : $this->prettyProductName($productName);
                if (mb_strlen($catalogName) < 3) {
                    $catalogName = $this->prettyProductName($this->familyName($lineDescription));
                }

                $spec = $this->specFromName($lineDescription);
                if ($spec === null && $this->isSpecLike($variantName)) {
                    $spec = $this->tidySpec($variantName);
                }

                $key = mb_strtolower($catalogName);
                if (! isset($buckets[$key])) {
                    $buckets[$key] = [
                        'name' => $catalogName,
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

                $pricing = $this->resolveLinePricing($variant, $row);
                $buckets[$key]['variants'][] = [
                    'name' => $spec,
                    'goods_description' => $goods !== '' ? $goods : $lineDescription,
                    'quantity' => $variant['quantity'] ?? $row['quantity'] ?? 1,
                    'unit' => $unit,
                    'hsn_code' => $pricing['hsn_code'],
                    'list_price' => $pricing['list_price'],
                    'discount_percent' => $pricing['discount_percent'],
                    'cost_price' => $pricing['cost_price'],
                    'cgst_amount' => $pricing['cgst_amount'],
                    'sgst_amount' => $pricing['sgst_amount'],
                    'igst_amount' => $pricing['igst_amount'],
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
     * @param  array<int, string>  $descriptions
     * @return array{product_id:int, product_name:string, match:string}|null
     */
    public function findDuplicateByGoodsDescription(int $shopId, array $descriptions): ?array
    {
        $needles = [];
        foreach ($descriptions as $description) {
            foreach ($this->goodsDescriptionKeys($description) as $key) {
                $needles[$key] = true;
            }
        }
        if ($needles === []) {
            return null;
        }

        $variants = ProductVariant::query()
            ->whereNotNull('goods_description')
            ->whereHas('product', fn ($query) => $query->where('shop_id', $shopId))
            ->with('product:id,name')
            ->get(['id', 'product_id', 'goods_description']);

        foreach ($variants as $variant) {
            foreach ($this->goodsDescriptionKeys($variant->goods_description) as $key) {
                if (isset($needles[$key])) {
                    return [
                        'product_id' => (int) $variant->product_id,
                        'product_name' => $variant->product?->name ?? '',
                        'match' => 'goods_description',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function goodsDescriptionKeys(mixed $value): array
    {
        $keys = [];
        foreach (preg_split("/\n+/", (string) $value) ?: [] as $line) {
            $key = $this->normalizeGoodsKey($line);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    protected function normalizeGoodsKey(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value);
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

    protected function nullableString(mixed $value, int $limit = 255): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || strtolower($value) === 'null' ? null : Str::limit($value, $limit, '');
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeExtractedLine(array $variant, array $row): array
    {
        $pricing = $this->resolveLinePricing($variant, $row);
        $attributes = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];

        return [
            'name' => $this->nullableString($variant['name'] ?? $variant['spec'] ?? null),
            'goods_description' => $this->nullableString($variant['goods_description'] ?? $row['goods_description'] ?? null, 2000),
            'quantity' => max(1, (int) ($variant['quantity'] ?? $row['quantity'] ?? 1)),
            'unit' => $this->nullableString($variant['unit'] ?? $row['unit'] ?? null) ?? 'pcs',
            'hsn_code' => $pricing['hsn_code'],
            'list_price' => $pricing['list_price'],
            'discount_percent' => $pricing['discount_percent'],
            'cost_price' => $pricing['cost_price'],
            'cgst_amount' => $pricing['cgst_amount'],
            'sgst_amount' => $pricing['sgst_amount'],
            'igst_amount' => $pricing['igst_amount'],
            'sku' => $this->nullableString($variant['sku'] ?? $row['sku'] ?? null),
            'attributes' => $this->normalizeAttributes($attributes),
        ];
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  array<string, mixed>  $row
     * @return array{hsn_code:?string, list_price:float, discount_percent:float, cost_price:float, cgst_amount:float, sgst_amount:float, igst_amount:float}
     */
    protected function resolveLinePricing(array $variant, array $row = []): array
    {
        $hsn = $this->normalizeHsn($variant['hsn_code'] ?? $variant['hsn'] ?? $row['hsn_code'] ?? $row['hsn'] ?? null);
        $list = $this->toMoney($variant['list_price'] ?? $variant['rate'] ?? $row['list_price'] ?? 0);
        $discount = $this->toPercent($variant['discount_percent'] ?? $variant['discount'] ?? $row['discount_percent'] ?? $row['discount'] ?? 0);
        $explicitCost = $this->toMoney($variant['cost_price'] ?? $row['cost_price'] ?? 0);

        if ($list <= 0 && $explicitCost > 0) {
            $list = $explicitCost;
        }

        if ($list > 0 && $discount > 0) {
            $cost = round($list * (1 - ($discount / 100)), 2);
        } elseif ($explicitCost > 0 && $explicitCost < $list) {
            $cost = $explicitCost;
        } elseif ($explicitCost > 0 && $list <= 0) {
            $cost = $explicitCost;
        } else {
            $cost = $list;
        }

        return [
            'hsn_code' => $hsn,
            'list_price' => $list,
            'discount_percent' => $discount,
            'cost_price' => $cost,
            'cgst_amount' => $this->toMoney($variant['cgst_amount'] ?? $variant['cgst'] ?? $row['cgst_amount'] ?? 0),
            'sgst_amount' => $this->toMoney($variant['sgst_amount'] ?? $variant['sgst'] ?? $row['sgst_amount'] ?? 0),
            'igst_amount' => $this->toMoney($variant['igst_amount'] ?? $variant['igst'] ?? $row['igst_amount'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, array<string, mixed>>  $products
     * @return array{supplier_name:?string, supplier_gstin:?string, invoice_number:?string, invoice_date:?string, cgst_amount:float, sgst_amount:float, igst_amount:float}
     */
    protected function normalizeInvoiceHeader(array $parsed, array $products): array
    {
        $cgst = $this->toMoney($parsed['cgst_amount'] ?? $parsed['cgst'] ?? 0);
        $sgst = $this->toMoney($parsed['sgst_amount'] ?? $parsed['sgst'] ?? 0);
        $igst = $this->toMoney($parsed['igst_amount'] ?? $parsed['igst'] ?? 0);

        if ($cgst <= 0 && $sgst <= 0 && $igst <= 0 && $products !== []) {
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }
                    $cgst += $this->toMoney($variant['cgst_amount'] ?? 0);
                    $sgst += $this->toMoney($variant['sgst_amount'] ?? 0);
                    $igst += $this->toMoney($variant['igst_amount'] ?? 0);
                }
            }
            $cgst = round($cgst, 2);
            $sgst = round($sgst, 2);
            $igst = round($igst, 2);
        }

        return [
            'supplier_name' => $this->nullableString($parsed['supplier_name'] ?? $parsed['supplier'] ?? null),
            'supplier_gstin' => $this->normalizeGstin($parsed['supplier_gstin'] ?? $parsed['gstin'] ?? $parsed['gst_no'] ?? null),
            'invoice_number' => $this->nullableString($parsed['invoice_number'] ?? null),
            'invoice_date' => $this->toDate($parsed['invoice_date'] ?? null),
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
        ];
    }

    protected function normalizeHsn(mixed $value): ?string
    {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) >= 4 && strlen($digits) <= 10) {
            return $digits;
        }

        $clean = preg_replace('/\s+/', '', $raw) ?? '';

        return $clean === '' ? null : Str::limit($clean, 16, '');
    }

    protected function normalizeGstin(mixed $value): ?string
    {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }

        $clean = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');

        return $clean === '' ? null : Str::limit($clean, 15, '');
    }

    protected function toPercent(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round(max(0, min(100, (float) $value)), 2);
        }

        $raw = trim((string) $value);
        $raw = str_replace(['%', ','], ['', ''], $raw);

        return round(max(0, min(100, (float) $raw)), 2);
    }

    protected function toDate(mixed $value): ?string
    {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd M Y', 'd-M-Y', 'd/m/y', 'd-m-y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);

                return $date?->toDateString();
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function sumMoney(array $values): float
    {
        $total = 0.0;
        foreach ($values as $value) {
            $total += $this->toMoney($value);
        }

        return round($total, 2);
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
