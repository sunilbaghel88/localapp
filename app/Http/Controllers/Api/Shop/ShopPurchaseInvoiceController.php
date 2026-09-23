<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Services\Products\PurchaseInvoiceAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopPurchaseInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $perPage = min((int) $request->get('per_page', 10), 50);
        $invoices = PurchaseInvoice::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->withCount('items')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    public function show(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $this->authorize('create', Product::class);
        $this->ownedInvoice($purchaseInvoice);

        $purchaseInvoice->load(['items.product', 'items.variant', 'shop']);

        return response()->json([
            'invoice' => $purchaseInvoice,
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $this->authorize('create', Product::class);
        $this->ownedInvoice($purchaseInvoice);

        $data = $request->validate([
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_gstin' => ['nullable', 'string', 'max:15'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'igst_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', Rule::exists('purchase_invoice_items', 'id')],
            'items.*.hsn_code' => ['nullable', 'string', 'max:16'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.list_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.igst_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseInvoice->update([
            'supplier_name' => array_key_exists('supplier_name', $data) ? $data['supplier_name'] : $purchaseInvoice->supplier_name,
            'supplier_gstin' => array_key_exists('supplier_gstin', $data) ? $data['supplier_gstin'] : $purchaseInvoice->supplier_gstin,
            'invoice_number' => array_key_exists('invoice_number', $data) ? $data['invoice_number'] : $purchaseInvoice->invoice_number,
            'invoice_date' => array_key_exists('invoice_date', $data) ? $data['invoice_date'] : $purchaseInvoice->invoice_date,
            'cgst_amount' => array_key_exists('cgst_amount', $data) ? $data['cgst_amount'] : $purchaseInvoice->cgst_amount,
            'sgst_amount' => array_key_exists('sgst_amount', $data) ? $data['sgst_amount'] : $purchaseInvoice->sgst_amount,
            'igst_amount' => array_key_exists('igst_amount', $data) ? $data['igst_amount'] : $purchaseInvoice->igst_amount,
        ]);

        foreach ($data['items'] ?? [] as $row) {
            $item = $purchaseInvoice->items()->where('id', $row['id'])->first();
            if (! $item) {
                continue;
            }

            $item->update([
                'hsn_code' => array_key_exists('hsn_code', $row) ? $row['hsn_code'] : $item->hsn_code,
                'quantity' => array_key_exists('quantity', $row) ? $row['quantity'] : $item->quantity,
                'list_price' => array_key_exists('list_price', $row) ? $row['list_price'] : $item->list_price,
                'discount_percent' => array_key_exists('discount_percent', $row) ? $row['discount_percent'] : $item->discount_percent,
                'cost_price' => array_key_exists('cost_price', $row) ? $row['cost_price'] : $item->cost_price,
                'selling_price' => array_key_exists('selling_price', $row) ? $row['selling_price'] : $item->selling_price,
                'cgst_amount' => array_key_exists('cgst_amount', $row) ? $row['cgst_amount'] : $item->cgst_amount,
                'sgst_amount' => array_key_exists('sgst_amount', $row) ? $row['sgst_amount'] : $item->sgst_amount,
                'igst_amount' => array_key_exists('igst_amount', $row) ? $row['igst_amount'] : $item->igst_amount,
            ]);

            if ($item->product_id && $item->hsn_code) {
                Product::query()->where('id', $item->product_id)->update([
                    'hsn_code' => $item->hsn_code,
                ]);
            }

            if ($item->product_variant_id) {
                $variantUpdate = [];
                if (array_key_exists('cost_price', $row)) {
                    $variantUpdate['cost_price'] = $item->cost_price;
                }
                if (array_key_exists('selling_price', $row)) {
                    $variantUpdate['price'] = $item->selling_price;
                }
                if (array_key_exists('quantity', $row)) {
                    $variantUpdate['stock'] = $item->quantity;
                }
                if ($variantUpdate !== []) {
                    ProductVariant::query()->where('id', $item->product_variant_id)->update($variantUpdate);
                }
            }
        }

        $purchaseInvoice->load(['items.product', 'items.variant', 'shop']);

        return response()->json([
            'invoice' => $purchaseInvoice,
        ]);
    }

    public function extract(Request $request, PurchaseInvoiceAiService $service): JsonResponse
    {
        $this->authorize('create', Product::class);

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $data = $request->validate([
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $upload = $request->file('file');
        if (! $upload) {
            throw ValidationException::withMessages([
                'file' => __('Please upload a PDF invoice.'),
            ]);
        }
        $extension = strtolower((string) $upload->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw ValidationException::withMessages([
                'file' => __('Please upload a PDF invoice.'),
            ]);
        }

        $shop = $this->ownedShop((int) $data['shop_id']);
        $stored = $upload->store('purchase-invoices', 'local');
        $absolute = Storage::disk('local')->path($stored);

        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            abort(401);
        }

        try {
            $result = $service->extract($shop, $absolute, $user);
        } finally {
            Storage::disk('local')->delete($stored);
        }

        return response()->json($result);
    }

    public function bulkCreate(Request $request, PurchaseInvoiceAiService $service): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'invoice' => ['nullable', 'array'],
            'invoice.supplier_name' => ['nullable', 'string', 'max:255'],
            'invoice.supplier_gstin' => ['nullable', 'string', 'max:15'],
            'invoice.invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice.invoice_date' => ['nullable', 'date'],
            'invoice.cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'invoice.sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'invoice.igst_amount' => ['nullable', 'numeric', 'min:0'],
            'invoice.source_filename' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.hsn_code' => ['nullable', 'string', 'max:16'],
            'items.*.skip_if_duplicate' => ['nullable', 'boolean'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.variants' => ['nullable', 'array', 'min:1'],
            'items.*.variants.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.variants.*.goods_description' => ['nullable', 'string', 'max:2000'],
            'items.*.variants.*.quantity' => ['required_with:items.*.variants', 'integer', 'min:0'],
            'items.*.variants.*.selling_price' => ['required_with:items.*.variants', 'numeric', 'min:0'],
            'items.*.variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.variants.*.list_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.variants.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.variants.*.hsn_code' => ['nullable', 'string', 'max:16'],
            'items.*.variants.*.cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.variants.*.sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.variants.*.igst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.variants.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.variants.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.variants.*.attributes' => ['nullable', 'array'],
        ]);

        $shop = $this->ownedShop((int) $data['shop_id']);

        $selected = collect($data['items'])
            ->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '')
            ->values()
            ->all();

        if ($selected === []) {
            throw ValidationException::withMessages([
                'items' => __('Select at least one product to create.'),
            ]);
        }

        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            abort(401);
        }

        $result = $service->bulkCreate(
            $shop,
            $user,
            (int) $data['category_id'],
            (string) $data['status'],
            $selected,
            $data['invoice'] ?? [],
        );

        return response()->json([
            'message' => __('Created :count products.', ['count' => count($result['created'])]),
            'created_count' => count($result['created']),
            'skipped_count' => count($result['skipped']),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'purchase_invoice' => $result['purchase_invoice'],
        ], 201);
    }

    protected function ownedShop(int $shopId): Shop
    {
        return Shop::query()
            ->where('user_id', Auth::id())
            ->where('id', $shopId)
            ->firstOrFail();
    }

    protected function ownedInvoice(PurchaseInvoice $invoice): void
    {
        $invoice->loadMissing('shop');
        if (! $invoice->shop || $invoice->shop->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
