<?php

namespace App\Http\Controllers\Electrician;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\CreateOrderWithItemsService;
use App\Services\Orders\OrderOnBehalfAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ElectricianOrderController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $shopTable = (new Shop)->getTable();
        $shops = $user->electricianShops()->orderBy($shopTable.'.name')->get();

        if ($shops->isEmpty()) {
            return redirect()
                ->route('electrician.dashboard')
                ->with('error', __('You are not attached to any shop yet. Ask a shop owner to add you as an electrician.'));
        }

        return view('electrician.orders.create', [
            'shops' => $shops,
        ]);
    }

    public function store(Request $request, CreateOrderWithItemsService $orderService): RedirectResponse
    {
        $user = $request->user();
        // Qualify column for PostgreSQL (shops + shop_user join: "id" is ambiguous).
        $shopTable = (new Shop)->getTable();
        $shops = $user->electricianShops()->pluck($shopTable.'.id');
        if ($shops->isEmpty()) {
            return redirect()
                ->route('electrician.dashboard')
                ->with('error', __('You are not attached to any shop.'));
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
            abort(403, __('You cannot create orders for this shop.'));
        }

        if ((int) $validated['user_id'] === $user->id) {
            return back()
                ->withInput()
                ->withErrors(['user_id' => __('Select the customer (not yourself).')]);
        }

        if (! empty($validated['address_id'])) {
            $address = Address::query()
                ->where('id', $validated['address_id'])
                ->where('user_id', $validated['user_id'])
                ->first();
            if (! $address) {
                return back()
                    ->withInput()
                    ->withErrors(['address_id' => __('The selected address does not belong to this customer.')]);
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
                deliveryMethod: ! empty($validated['address_id']) ? 'home_delivery' : 'pickup',
                deliveryAgentUserId: null,
                deliveryCharge: 0,
                items: $items,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('electrician.dashboard')
            ->with('success', __('Order #:id created for the customer.', ['id' => $order->id]));
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

    public function aiSuggestProducts(Request $request): JsonResponse
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

        $result = app(OrderOnBehalfAiService::class)->suggest($shopId, $prompt, $request->user());

        return response()->json($result);
    }
}
