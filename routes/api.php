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

// Public routes
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/register', [AuthController::class, 'register'])->name('api.register');

Route::get('/home', [HomeController::class, 'index'])->name('api.home');
Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('api.products.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
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

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index'])->name('api.addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('api.addresses.store');
    Route::patch('/addresses/{address}', [AddressController::class, 'update'])->name('api.addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('api.addresses.destroy');
    Route::post('/addresses/{address}/set-default', [AddressController::class, 'setDefault'])->name('api.addresses.set-default');
});
