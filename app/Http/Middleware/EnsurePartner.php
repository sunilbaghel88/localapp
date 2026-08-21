<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartner
{
    /**
     * Ensure the authenticated user is a reward-eligible partner
     * (electrician, plumber, or any type linked on a shop type).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isPartner') || ! $user->isPartner()) {
            abort(403);
        }

        return $next($request);
    }
}
