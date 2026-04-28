<?php

namespace App\Http\Controllers\Api\Electrician;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Product;
use App\Models\RewardRedemptionRequest;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserRewardGrant;
use App\Services\Orders\CreateOrderWithItemsService;
use App\Services\Orders\OrderOnBehalfAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectricianApiController extends Controller
{
    public function shops(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopTable = (new Shop)->getTable();
        $shops = $user->electricianShops()->orderBy($shopTable.'.name')->get();

        return response()->json([
            'shops' => $shops->map(fn (Shop $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
            ]),
        ]);
    }

    public function rewardGrants(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $user = $request->user();

        $grants = UserRewardGrant::query()
            ->with(['order.shop', 'grantedBy'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        $totalGrantedAudit = (int) UserRewardGrant::query()
            ->where('user_id', $user->id)
            ->sum('points');

        return response()->json(array_merge(
            $grants->toArray(),
            [
                'current_points' => (int) ($user->reward_points ?? 0),
                'total_granted_audit' => $totalGrantedAudit,
            ]
        ));
    }

    public function rewardRedemptions(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $user = $request->user();

        $requests = RewardRedemptionRequest::query()
            ->with(['shop:id,name', 'approvedBy:id,name'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($requests);
    }

    public function createRewardRedemption(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopTable = (new Shop)->getTable();
        $allowedShopIds = $user->electricianShops()->pluck($shopTable.'.id');

        if ($allowedShopIds->isEmpty()) {
            return response()->json([
                'message' => __('You are not attached to any shop yet.'),
            ], 403);
        }

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'requested_points' => ['required', 'integer', 'min:1'],
            'redemption_type' => ['required', 'string', 'in:cash,gift'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $allowedShopIds->contains((int) $validated['shop_id'])) {
            return response()->json([
                'message' => __('You cannot redeem points with this shop.'),
            ], 403);
        }

        if ((int) $validated['requested_points'] > (int) $user->reward_points) {
            return response()->json([
                'message' => __('Requested points exceed your available balance.'),
            ], 422);
        }

        $created = RewardRedemptionRequest::create([
            'user_id' => $user->id,
            'shop_id' => (int) $validated['shop_id'],
            'requested_points' => (int) $validated['requested_points'],
            'redemption_type' => $validated['redemption_type'],
            'status' => 'pending',
            'note' => $validated['note'] ?? null,
        ]);

        $created->load(['shop:id,name', 'approvedBy:id,name']);

        return response()->json([
            'message' => __('Redemption request submitted.'),
            'request' => $created,
        ], 201);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
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

    public function searchProducts(Request $request): JsonResponse
    {
        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $shopId = (int) $request->query('shop_id');
        $user = $request->user();

        $shopTable = (new Shop)->getTable();
        if (! $user->electricianShops()->where($shopTable.'.id', $shopId)->exists()) {
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
        $user = $request->user();
        if ($customerId === $user->id) {
            return response()->json(['data' => []]);
        }

        User::query()->findOrFail($customerId);

        $addresses = Address::query()
            ->where('user_id', $customerId)
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

    public function aiSuggest(Request $request, OrderOnBehalfAiService $ai): JsonResponse
    {
        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $shopId = (int) $request->input('shop_id');
        $prompt = trim((string) $request->input('prompt'));

        $shopTable = (new Shop)->getTable();
        if (! $request->user()->electricianShops()->where($shopTable.'.id', $shopId)->exists()) {
            abort(403);
        }

        try {
            $result = $ai->suggest($shopId, $prompt, $request->user());
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
        $user = $request->user();
        $shopTable = (new Shop)->getTable();
        $shops = $user->electricianShops()->pluck($shopTable.'.id');
        if ($shops->isEmpty()) {
            return response()->json(['message' => __('You are not attached to any shop.')], 403);
        }

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $shops->contains((int) $validated['shop_id'])) {
            return response()->json(['message' => __('You cannot create orders for this shop.')], 403);
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
                electricianUserId: $user->id,
                items: $items,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('Could not create order.'),
                'errors' => $e->errors(),
            ], 422);
        }

        $order->load(['shop', 'address', 'items']);

        return response()->json(['order' => $order], 201);
    }
}
