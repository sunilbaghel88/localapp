<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with('shop', 'items.variant.product')
            ->latest()
            ->paginate(10);

        return view('shop.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        // Verify order belongs to user
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        $order->load([
            'shop',
            'address',
            'items.variant.product.images',
            'payments'
        ]);

        return view('shop.orders.show', compact('order'));
    }
}
