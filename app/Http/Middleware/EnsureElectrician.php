<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureElectrician
{
    /**
     * Ensure the authenticated user is an electrician.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isElectrician') || ! $user->isElectrician()) {
            abort(403);
        }

        return $next($request);
    }
}

