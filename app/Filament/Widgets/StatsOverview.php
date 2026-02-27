<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $shopIds = $user->shops()->pluck('id');

        $totalOrders = Order::whereIn('shop_id', $shopIds)->count();
        $totalRevenue = Order::whereIn('shop_id', $shopIds)
            ->where('payment_status', 'paid')
            ->sum('grand_total');
        $totalProducts = Product::whereHas('shop', fn ($q) => $q->where('user_id', $user->id))->count();
        $pendingOrders = Order::whereIn('shop_id', $shopIds)
            ->where('status', 'pending')
            ->count();

        return [
            Stat::make('Total Orders', Number::format($totalOrders))
                ->description('All time orders')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),
            Stat::make('Total Revenue', '₹' . Number::format($totalRevenue, 0))
                ->description('From paid orders')
                ->descriptionIcon('heroicon-m-currency-rupee')
                ->color('success'),
            Stat::make('Total Products', Number::format($totalProducts))
                ->description('Active products')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),
            Stat::make('Pending Orders', Number::format($pendingOrders))
                ->description('Requires attention')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),
        ];
    }
}

