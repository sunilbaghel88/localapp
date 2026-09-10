<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.selling_price' => ['required', 'numeric', 'min:0'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.skip_if_duplicate' => ['nullable', 'boolean'],
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
        );

        return response()->json([
            'message' => __('Created :count products.', ['count' => count($result['created'])]),
            'created_count' => count($result['created']),
            'skipped_count' => count($result['skipped']),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ], 201);
    }

    protected function ownedShop(int $shopId): Shop
    {
        return Shop::query()
            ->where('user_id', Auth::id())
            ->where('id', $shopId)
            ->firstOrFail();
    }
}
