<?php

use App\Http\Controllers\Web\PhoneAuthController;
use App\Http\Middleware\RequireSellerOnboarding;
use App\Http\Middleware\RequireVerifiedPhone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'phone.verified' => RequireVerifiedPhone::class,
            'seller.ready' => RequireSellerOnboarding::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/kirish');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code']);
        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->expectsJson() && $request->routeIs('phone.send', 'phone.resend', 'phone.verify.store')) {
                return app(PhoneAuthController::class)->rateLimitedResponse($request, $exception);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => $request->is('api/*') || $request->expectsJson());
    })->create();
