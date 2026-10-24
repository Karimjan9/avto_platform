<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSellerOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasCompletedSellerProfile()) {
            return $next($request);
        }
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'E’lon joylashtirishdan oldin sotuvchi profilingizni to‘ldiring.',
                'code' => 'seller_onboarding_required',
            ], 403);
        }
        if ($request->isMethod('GET') && ! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('seller.onboarding');
    }
}
