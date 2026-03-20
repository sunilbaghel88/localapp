<?php

use App\Http\Controllers\Electrician\DashboardController;
use App\Http\Controllers\Electrician\RewardPointsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'electrician'])
    ->prefix('electrician')
    ->name('electrician.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reward-points', [RewardPointsController::class, 'index'])->name('rewards.index');
    });

