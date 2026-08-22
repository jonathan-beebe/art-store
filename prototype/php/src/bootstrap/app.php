<?php

use App\Http\Middleware\ResolveCustomerIdentity;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.seller' => Authenticate::using('seller'),
            'auth.customer' => Authenticate::using('customer'),
            'customer.identity' => ResolveCustomerIdentity::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('seller', 'seller/*')
            ? route('auth.seller.login')
            : route('auth.customer.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
