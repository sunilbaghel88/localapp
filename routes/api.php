<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\UserTypeController;
use App\Http\Controllers\Api\Shop\ShopMetaController;
use App\Http\Controllers\Api\Shop\ShopProductController;
use App\Http\Controllers\Api\Shop\ShopOrderController;

// Public routes
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/register', [AuthController::class, 'register'])->name('api.register');

Route::get('/home', [HomeController::class, 'index'])->name('api.home');
Route::get('/user-types', [UserTypeController::class, 'index'])->name('api.user-types.index');
Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('api.products.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $role = $user?->roles()->pluck('name')->first();
        $permissions = $user?->getAllPermissions()->pluck('name') ?? collect();

        return array_merge($user?->toArray() ?? [], [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // Cart
    Route::get('/cart', [CartController::class, 'index'])->name('api.cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('api.cart.add');
    Route::patch('/cart/update/{cartItem}', [CartController::class, 'update'])->name('api.cart.update');
    Route::delete('/cart/remove/{cartItem}', [CartController::class, 'remove'])->name('api.cart.remove');

    // Checkout
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('api.checkout.index');
    Route::post('/checkout/address', [CheckoutController::class, 'storeAddress'])->name('api.checkout.address');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('api.checkout.store');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('api.orders.show');

    // Shop owner management APIs
    Route::prefix('shop')->group(function () {
        // Metadata
        Route::get('/shops', [ShopMetaController::class, 'shops']);
        Route::get('/categories', [ShopMetaController::class, 'categories']);
        Route::get('/brands', [ShopMetaController::class, 'brands']);

        // Products
        Route::get('/products', [ShopProductController::class, 'index']);
        Route::post('/products', [ShopProductController::class, 'store']);
        Route::get('/products/{product}', [ShopProductController::class, 'show']);
        Route::patch('/products/{product}', [ShopProductController::class, 'update']);

        // Orders
        Route::get('/orders', [ShopOrderController::class, 'index']);
        Route::get('/orders/{order}', [ShopOrderController::class, 'show']);
        Route::patch('/orders/{order}', [ShopOrderController::class, 'update']);
    });

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index'])->name('api.addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('api.addresses.store');
    Route::patch('/addresses/{address}', [AddressController::class, 'update'])->name('api.addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('api.addresses.destroy');
    Route::post('/addresses/{address}/set-default', [AddressController::class, 'setDefault'])->name('api.addresses.set-default');
});
