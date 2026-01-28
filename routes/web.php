<?php

use App\Http\Controllers\Shop\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
});
require __DIR__.'/auth.php';
