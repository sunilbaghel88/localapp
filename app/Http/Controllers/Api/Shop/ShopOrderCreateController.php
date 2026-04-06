<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\CreateOrderWithItemsService;
use App\Services\Orders\OrderOnBehalfAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopOrderCreateController extends Controller
{
    protected function userOwnsShop(int $shopId): bool
    {
        return Shop::query()
            ->where('user_id', Auth::id())
            ->where('id', $shopId)
            ->exists();
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($query) use ($needle) {
                $query->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle);
                if (DB::getSchemaBuilder()->hasColumn('users', 'phone')) {
                    $query->orWhere('phone', 'like', $needle);
                }
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone ?? null,
                'label' => $u->name.' — '.($u->email ?? '').($u->phone ? ' · '.$u->phone : ''),
            ]),
        ]);
    }

    /**
     * Electricians attached to the shop with the shop type's electrician user type (same options as Filament order create).
     */
    public function listElectricians(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
        ]);

        $shopId = (int) $request->query('shop_id');

        if (! $this->userOwnsShop($shopId)) {
            abort(403);
        }

        $shop = Shop::query()
            ->with('shopType')
            ->findOrFail($shopId);

        $electricianUserTypeId = $shop->shopType?->electrician_user_type_id;
        if (! $electricianUserTypeId) {
            return response()->json(['data' => []]);
        }

        $rows = $shop->electricians()
            ->where('users.user_type_id', $electricianUserTypeId)
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email', 'users.phone']);

        return response()->json([
            'data' => $rows->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone ?? null,
                'label' => $u->name.($u->phone ? ' ('.$u->phone.')' : ''),
            ]),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $shopId = (int) $request->query('shop_id');

        if (! $this->userOwnsShop($shopId)) {
            abort(403);
        }

        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->where('shop_id', $shopId)
            ->whereRaw('LOWER(name) like ?', ['%'.strtolower($q).'%'])
            ->with([
                'brand',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
            ])
            ->orderBy('name')
            ->limit(30)
            ->get();

        $data = $products
            ->filter(fn (Product $product) => $product->variants->isNotEmpty())
            ->values()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand?->name,
                    'variants' => $product->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'label' => $v->name ?: $v->sku ?: '#'.$v->id,
                        'price' => (float) $v->price,
                        'stock' => (int) $v->stock,
                    ])->values(),
                ];
            });

        return response()->json(['data' => $data]);
    }

    public function customerAddresses(Request $request, int $customerId): JsonResponse
    {
        $this->authorize('create', Order::class);

        $user = $request->user();
        if ($customerId === $user->id) {
            return response()->json(['data' => []]);
        }

        $customer = User::query()->findOrFail($customerId);

        $addresses = Address::query()
            ->where('user_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $addresses->map(fn (Address $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'line' => trim($a->address_line1.' '.$a->city.' '.$a->postal_code),
            ]),
        ]);
    }

    public function aiSuggest(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $shopId = (int) $request->input('shop_id');
        $prompt = trim((string) $request->input('prompt'));

        if (! $this->userOwnsShop($shopId)) {
            abort(403);
        }

        try {
            $result = app(OrderOnBehalfAiService::class)->suggest($shopId, $prompt, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($result);
    }

    public function store(Request $request, CreateOrderWithItemsService $orderService): JsonResponse
    {
        $this->authorize('create', Order::class);

        $user = $request->user();

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'electrician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $this->userOwnsShop((int) $validated['shop_id'])) {
            abort(403, __('You cannot create orders for this shop.'));
        }

        if ((int) $validated['user_id'] === $user->id) {
            throw ValidationException::withMessages([
                'user_id' => [__('Select the customer (not yourself).')],
            ]);
        }

        if (! empty($validated['address_id'])) {
            $address = Address::query()
                ->where('id', $validated['address_id'])
                ->where('user_id', $validated['user_id'])
                ->first();
            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => [__('The selected address does not belong to this customer.')],
                ]);
            }
        }

        $electricianUserId = isset($validated['electrician_user_id']) ? (int) $validated['electrician_user_id'] : null;
        if ($electricianUserId !== null) {
            $shopModel = Shop::query()
                ->with('shopType')
                ->findOrFail((int) $validated['shop_id']);

            $electricianUserTypeId = $shopModel->shopType?->electrician_user_type_id;
            if (! $electricianUserTypeId) {
                throw ValidationException::withMessages([
                    'electrician_user_id' => [__('This shop does not support assigning an electrician.')],
                ]);
            }

            $validElectrician = $shopModel->electricians()
                ->where('users.id', $electricianUserId)
                ->where('users.user_type_id', $electricianUserTypeId)
                ->where('users.is_active', true)
                ->exists();

            if (! $validElectrician) {
                throw ValidationException::withMessages([
                    'electrician_user_id' => [__('The selected electrician is not valid for this shop.')],
                ]);
            }
        }

        $items = [];
        foreach ($validated['items'] as $row) {
            $items[] = [
                'product_id' => (int) $row['product_id'],
                'product_variant_id' => isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null,
                'quantity' => (int) $row['quantity'],
            ];
        }

        try {
            $order = $orderService->create(
                customerUserId: (int) $validated['user_id'],
                shopId: (int) $validated['shop_id'],
                addressId: $validated['address_id'] ?? null,
                electricianUserId: $electricianUserId,
                items: $items,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('Could not create order.'),
                'errors' => $e->errors(),
            ], 422);
        }

        $order->load(['shop', 'address', 'items']);

        return response()->json([
            'order' => $order,
        ], 201);
    }
}
