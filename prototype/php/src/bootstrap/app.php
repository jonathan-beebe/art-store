<?php

declare(strict_types=1);

use App\Domain\DomainRuleViolation;
use App\Http\Middleware\LogRequestStory;
use App\Http\Middleware\ResolveCustomerIdentity;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // Listener discovery reflects over every file under app/Listeners, and the
    // sidecar test beside each listener is one of them. AppServiceProvider
    // names the event/listener pairs instead.
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Appended rather than prepended: the request marks belong on every
        // line the request writes, and naming the actor from their guard
        // needs the session the group starts.
        $middleware->web(append: LogRequestStory::class);

        $middleware->alias([
            'auth.seller' => Authenticate::using('seller'),
            'auth.customer' => Authenticate::using('customer'),
            'auth.admin' => Authenticate::using('admin'),
            'customer.identity' => ResolveCustomerIdentity::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('seller', 'seller/*') => route('auth.seller.login'),
            $request->is('admin', 'admin/*') => route('auth.admin.login'),
            default => route('auth.customer.login'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every rule the core refuses reaches the person who tripped it as a
        // message on the page they submitted from, the way a failed validation
        // rule does.
        $exceptions->render(fn (DomainRuleViolation $violation) => back()
            ->withInput()
            ->withErrors($violation->getMessage()));
    })->create();
