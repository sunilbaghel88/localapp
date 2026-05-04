<?php

namespace App\Http\Controllers\Api\Shop;

use App\Actions\GrantOrderRewardPoints;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ShopOrderController extends Controller
{
    protected function shopIdsForCurrentUser(): array
    {
        return Auth::user()->shops()->pluck('id')->all();
    }

    protected function ensureOrderBelongsToCurrentUser(Order $order, array $shopIds): void
    {
        if (! in_array((int) $order->shop_id, $shopIds, true)) {
            abort(403);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $shopIds = $this->shopIdsForCurrentUser();

        $orders = Order::query()
            ->whereIn('shop_id', $shopIds)
            ->with([
                'shop:id,name,slug,status',
            ])
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $shopIds = $this->shopIdsForCurrentUser();
        $this->ensureOrderBelongsToCurrentUser($order, $shopIds);

        $this->authorize('view', $order);

        $order->load([
            'shop',
            'address',
            'items',
            'user:id,name,email,phone',
            'electricianUser:id,name,email,phone',
        ]);

        return response()->json([
            'order' => $order,
        ]);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $shopIds = $this->shopIdsForCurrentUser();
        $this->ensureOrderBelongsToCurrentUser($order, $shopIds);

        $this->authorize('update', $order);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
        ]);

        $order->update($data);

        $order->load([
            'shop',
            'address',
            'items',
            'user:id,name,email,phone',
            'electricianUser:id,name,email,phone',
        ]);

        return response()->json([
            'order' => $order,
        ]);
    }

    public function grantReward(Request $request, Order $order): JsonResponse
    {
        $shopIds = $this->shopIdsForCurrentUser();
        $this->ensureOrderBelongsToCurrentUser($order, $shopIds);

        $this->authorize('update', $order);

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        GrantOrderRewardPoints::handle($order, $data);

        $order->load([
            'shop',
            'address',
            'items',
            'user:id,name,email,phone',
            'electricianUser:id,name,email,phone',
        ]);

        return response()->json([
            'message' => 'Reward points granted successfully.',
            'order' => $order,
        ]);
    }
}

