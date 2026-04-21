<?php

use App\Http\Controllers\Electrician\DashboardController;
use App\Http\Controllers\Electrician\ElectricianOrderController;
use App\Http\Controllers\Electrician\RewardPointsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'electrician'])
    ->prefix('electrician')
    ->name('electrician.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reward-points', [RewardPointsController::class, 'index'])->name('rewards.index');
        Route::post('/reward-points/redeem', [RewardPointsController::class, 'storeRedemptionRequest'])->name('rewards.redeem.store');

        Route::get('/order-create', [ElectricianOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [ElectricianOrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/ai-suggest', [ElectricianOrderController::class, 'aiSuggestProducts'])->name('orders.ai-suggest');
        Route::get('/orders/search/customers', [ElectricianOrderController::class, 'searchCustomers'])->name('orders.search-customers');
        Route::get('/orders/search/products', [ElectricianOrderController::class, 'searchProducts'])->name('orders.search-products');
        Route::get('/orders/customers/{customerId}/addresses', [ElectricianOrderController::class, 'customerAddresses'])->name('orders.customer-addresses');
    });

