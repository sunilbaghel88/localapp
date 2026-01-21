<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->role == 'admin' || $request->user()->role == 'owner') {
            return $next($request);
        }

        abort(403, 'Only shop owners can access this area.');
    }
}
