<?php

use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\PartnerOrderController;
use App\Http\Controllers\Partner\RewardPointsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'partner'])
    ->prefix('partner')
    ->name('partner.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reward-points', [RewardPointsController::class, 'index'])->name('rewards.index');
        Route::post('/reward-points/redeem', [RewardPointsController::class, 'storeRedemptionRequest'])->name('rewards.redeem.store');

        Route::get('/order-create', [PartnerOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [PartnerOrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/ai-suggest', [PartnerOrderController::class, 'aiSuggestProducts'])->name('orders.ai-suggest');
        Route::get('/orders/search/customers', [PartnerOrderController::class, 'searchCustomers'])->name('orders.search-customers');
        Route::get('/orders/search/products', [PartnerOrderController::class, 'searchProducts'])->name('orders.search-products');
        Route::get('/orders/customers/{customerId}/addresses', [PartnerOrderController::class, 'customerAddresses'])->name('orders.customer-addresses');
    });

Route::middleware(['auth', 'partner'])->get('/electrician/{path?}', function (?string $path = null) {
    return redirect('/partner'.($path ? '/'.$path : ''), 301);
})->where('path', '.*');
