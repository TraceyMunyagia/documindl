<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $current = $user->currentBusiness;

        if ($current === null || ! $user->belongsToBusiness($current)) {
            $fallback = $user->businesses()->orderBy('businesses.name')->first();

            if ($fallback === null) {
                return redirect()->route('businesses.create');
            }

            $user->switchBusiness($fallback);
        }

        return $next($request);
    }
}
