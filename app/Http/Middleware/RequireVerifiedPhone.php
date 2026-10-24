<?php

namespace App\Http\Middleware;

use App\Services\PhoneOtpService;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedPhone
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $verified = false;
        if ($user?->phone_verified_at && is_string($user->phone)) {
            try {
                $verified = PhoneOtpService::normalizePhone($user->phone) === $user->phone;
            } catch (InvalidArgumentException) {
            }
        }
        if ($verified) {
            return $next($request);
        }
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'E’lon joylash uchun telefon raqamingizni tasdiqlang.', 'code' => 'phone_verification_required'], 403);
        }
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('phone.start');
    }
}
