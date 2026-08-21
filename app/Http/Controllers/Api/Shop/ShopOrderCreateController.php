<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserType;
use App\Services\Orders\CreateOrderWithItemsService;
use App\Services\Orders\OrderOnBehalfAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

    protected function deliveryAgentUserTypeId(): ?int
    {
        return UserType::query()
            ->where('is_active', true)
            ->whereIn('slug', ['delivery-agent', 'delivery_agent'])
            ->value('id');
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
                $query->whereNameLike($needle)
                    ->orWhere('email', 'like', $needle);
                if (DB::getSchemaBuilder()->hasColumn('users', 'phone')) {
                    $query->orWhere('phone', 'like', $needle);
                }
            })
            ->orderByName()
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

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
     * Partners attached to the shop whose types are reward-eligible for this shop type.
     */
    public function listPartners(Request $request): JsonResponse
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
            ->with(['shopType.rewardUserTypes'])
            ->findOrFail($shopId);

        $rewardTypeIds = $shop->shopType?->rewardUserTypeIds() ?? [];
        if (! ($shop->shopType?->supports_partner_rewards) || $rewardTypeIds === []) {
            return response()->json(['data' => []]);
        }

        $rows = $shop->partners()
            ->withAnyUserTypeIds($rewardTypeIds)
            ->where('users.is_active', true)
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.phone']);

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

    public function listDeliveryAgents(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
        ]);

        $shopId = (int) $request->query('shop_id');

        if (! $this->userOwnsShop($shopId)) {
            abort(403);
        }

        $deliveryAgentUserTypeId = $this->deliveryAgentUserTypeId();
        if (! $deliveryAgentUserTypeId) {
            return response()->json(['data' => []]);
        }

        $shop = Shop::query()->findOrFail($shopId);

        $rows = $shop->partners()
            ->withUserTypeId($deliveryAgentUserTypeId)
            ->where('users.is_active', true)
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.phone']);

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

    /**
     * Register-style customer record for order-on-behalf.
     * New customers are left without user types so they default to Customer.
     */
    public function storeCustomer(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'label' => $user->name.' — '.($user->email ?? '').($user->phone ? ' · '.$user->phone : ''),
            ],
        ], 201);
    }

    /**
     * Create a partner user for this shop type and attach them to the shop.
     */
    public function storePartner(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_type_id' => ['nullable', 'integer', 'exists:user_types,id'],
        ]);

        $shopId = (int) $validated['shop_id'];

        if (! $this->userOwnsShop($shopId)) {
            abort(403);
        }

        $shop = Shop::query()
            ->with(['shopType.rewardUserTypes'])
            ->findOrFail($shopId);

        $rewardTypeIds = $shop->shopType?->rewardUserTypeIds() ?? [];
        if (! ($shop->shopType?->supports_partner_rewards) || $rewardTypeIds === []) {
            throw ValidationException::withMessages([
                'shop_id' => [__('This shop does not support partner rewards.')],
            ]);
        }

        $partnerTypeId = isset($validated['user_type_id'])
            ? (int) $validated['user_type_id']
            : (int) ($rewardTypeIds[0] ?? 0);

        if (! in_array($partnerTypeId, $rewardTypeIds, true)) {
            throw ValidationException::withMessages([
                'user_type_id' => [__('Select a reward-eligible user type for this shop.')],
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);
        $user->userTypes()->syncWithoutDetaching([$partnerTypeId]);

        $shop->partners()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'label' => $user->name.($user->phone ? ' ('.$user->phone.')' : ''),
            ],
        ], 201);
    }

    public function storeDeliveryAgent(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $shopId = (int) $validated['shop_id'];

        if (! $this->userOwnsShop($shopId)) {
            abort(403);
        }

        $deliveryAgentUserTypeId = $this->deliveryAgentUserTypeId();
        if (! $deliveryAgentUserTypeId) {
            throw ValidationException::withMessages([
                'shop_id' => [__('Delivery agent user type is not configured.')],
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);
        $user->userTypes()->syncWithoutDetaching([$deliveryAgentUserTypeId]);

        $shop = Shop::query()->findOrFail($shopId);
        $shop->partners()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'label' => $user->name.($user->phone ? ' ('.$user->phone.')' : ''),
            ],
        ], 201);
    }

    public function storeCustomerAddress(Request $request, int $customerId): JsonResponse
    {
        $this->authorize('create', Order::class);

        $user = $request->user();
        if ($customerId === $user->id) {
            throw ValidationException::withMessages([
                'customer' => [__('Cannot add an address for your own account from this flow.')],
            ]);
        }

        User::query()->findOrFail($customerId);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            Address::query()
                ->where('user_id', $customerId)
                ->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => $customerId,
            'label' => $validated['label'] ?? null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'state' => $validated['state'],
            'country' => $validated['country'],
            'postal_code' => $validated['postal_code'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        if (Address::query()->where('user_id', $customerId)->count() === 1) {
            $address->update(['is_default' => true]);
        }

        return response()->json([
            'data' => [
                'id' => $address->id,
                'label' => $address->label,
                'line' => trim($address->address_line1.' '.$address->city.' '.$address->postal_code),
            ],
        ], 201);
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
            'delivery_method' => ['required', 'string', 'in:pickup,home_delivery'],
            'delivery_agent_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
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

        $deliveryMethod = (string) $validated['delivery_method'];
        if ($deliveryMethod === 'pickup') {
            $validated['address_id'] = null;
            $validated['delivery_agent_user_id'] = null;
            $validated['delivery_charge'] = 0;
        }

        if ($deliveryMethod === 'home_delivery' && empty($validated['address_id'])) {
            throw ValidationException::withMessages([
                'address_id' => [__('Shipping address is required for home delivery.')],
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

        $deliveryAgentUserId = isset($validated['delivery_agent_user_id']) ? (int) $validated['delivery_agent_user_id'] : null;
        $deliveryCharge = isset($validated['delivery_charge']) ? (float) $validated['delivery_charge'] : 0;
        if ($deliveryMethod === 'home_delivery') {
            if ($deliveryAgentUserId === null) {
                throw ValidationException::withMessages([
                    'delivery_agent_user_id' => [__('Select a delivery agent for home delivery.')],
                ]);
            }

            $deliveryAgentUserTypeId = $this->deliveryAgentUserTypeId();
            if (! $deliveryAgentUserTypeId) {
                throw ValidationException::withMessages([
                    'delivery_agent_user_id' => [__('Delivery agent user type is not configured.')],
                ]);
            }

            $shopModel = Shop::query()->findOrFail((int) $validated['shop_id']);
            $validDeliveryAgent = $shopModel->partners()
                ->where('users.id', $deliveryAgentUserId)
                ->withUserTypeId($deliveryAgentUserTypeId)
                ->where('users.is_active', true)
                ->exists();

            if (! $validDeliveryAgent) {
                throw ValidationException::withMessages([
                    'delivery_agent_user_id' => [__('The selected delivery agent is not valid for this shop.')],
                ]);
            }
        }

        $electricianUserId = isset($validated['electrician_user_id']) ? (int) $validated['electrician_user_id'] : null;
        if ($electricianUserId !== null) {
            $shopModel = Shop::query()
                ->with(['shopType.rewardUserTypes'])
                ->findOrFail((int) $validated['shop_id']);

            $rewardTypeIds = $shopModel->shopType?->rewardUserTypeIds() ?? [];
            if (! ($shopModel->shopType?->supports_partner_rewards) || $rewardTypeIds === []) {
                throw ValidationException::withMessages([
                    'electrician_user_id' => [__('This shop does not support assigning a partner.')],
                ]);
            }

            $validPartner = $shopModel->partners()
                ->where('users.id', $electricianUserId)
                ->withAnyUserTypeIds($rewardTypeIds)
                ->where('users.is_active', true)
                ->exists();

            if (! $validPartner) {
                throw ValidationException::withMessages([
                    'electrician_user_id' => [__('The selected partner is not valid for this shop.')],
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
                deliveryMethod: $deliveryMethod,
                deliveryAgentUserId: $deliveryAgentUserId,
                deliveryCharge: $deliveryCharge,
                items: $items,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('Could not create order.'),
                'errors' => $e->errors(),
            ], 422);
        }

        $order->load(['shop', 'address', 'items', 'deliveryAgentUser']);

        return response()->json([
            'order' => $order,
        ], 201);
    }
}
