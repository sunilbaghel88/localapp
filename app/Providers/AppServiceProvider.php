<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        if ($request->secure() || $request->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
