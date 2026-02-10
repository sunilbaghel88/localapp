<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', Auth::id())
            ->with('shop', 'items.variant.product')
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json(['orders' => $orders]);
    }

    public function show(Order $order): JsonResponse
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $order->load([
            'shop',
            'address',
            'items.variant.product.images',
            'payments',
        ]);

        return response()->json(['order' => $order]);
    }
}
