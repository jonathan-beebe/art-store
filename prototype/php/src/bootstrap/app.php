<?php

declare(strict_types=1);

use App\Domain\DomainRuleViolation;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Http\Middleware\LogRequestStory;
use App\Http\Middleware\NameRequestVisitor;
use App\Http\Middleware\ResolveCustomerIdentity;
use App\Http\Middleware\RollUpPageViews;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Requests\Shop\ShopRequest;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

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
        // Outermost in the application: a request that matches no route and
        // one the forgery guard refuses never reach a group, and both are
        // requests the log has to account for.
        $middleware->prepend(LogRequestStory::class);

        // Global rather than the `web` group, for the same reason: a route
        // that matches nothing still answers with every header docs/
        // alignment.md's security-headers section names, the way its 404
        // already carries `X-Request-Id` from the middleware above.
        $middleware->append(SecurityHeaders::class);

        // Global for the same reason again: a hit against any of the three
        // sites rolls up, and a route that matches nothing is counted
        // against nothing. Terminable rather than answered from `handle()`
        // — the write happens after the response has already gone back, so
        // it costs the roll-up nothing on the request it counts.
        $middleware->append(RollUpPageViews::class);

        // Appended to the group instead, because the `sid` cookie is only
        // readable after the group decrypts cookies and a guard only names
        // the actor after the group starts the session.
        $middleware->web(append: NameRequestVisitor::class);

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

        // docs/alignment.md §3: the client ip is the socket's own unless an
        // operator names the proxy in front of it. TrustProxies is already in
        // the global stack with nothing configured, which is what leaves
        // `$request->ip()` reading the socket in development.
        $trustedProxies = env('TRUSTED_PROXIES');

        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
                headers: Request::HEADER_X_FORWARDED_FOR,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A card number reaches the session's old input from nowhere: the
        // storefront's forms render `old('card_number')` back into the field,
        // so flashing one would write it into the next response body and hold
        // it in session storage.
        $exceptions->dontFlash(ShopRequest::CARD_FIELDS);

        // Every rule the core refuses reaches the person who tripped it as a
        // message on the page they submitted from, the way a failed validation
        // rule does.
        $exceptions->render(fn (DomainRuleViolation $violation, Request $request) => back()
            ->withInput(Arr::except($request->input(), ShopRequest::CARD_FIELDS))
            ->withErrors($violation->getMessage()));

        // docs/alignment.md §3's default 429: the site's own page, in its own
        // layout, named by the path the way `redirectGuestsTo` above already
        // picks a login route by it. Every route the visitor reached by
        // filling in a field catches this itself first and re-renders the
        // page that field sits on, so what is left here is the routes with
        // no field to give back: the support and order-thread buttons on the
        // shop and seller sites, and the magic-link verification GET.
        $exceptions->render(function (RateLimitExceeded $exceeded, Request $request): Response {
            $view = match (true) {
                $request->is('admin', 'admin/*') => 'errors.rate-limited-admin',
                $request->is('seller', 'seller/*') => 'errors.rate-limited-seller',
                default => 'errors.rate-limited-shop',
            };

            $message = "Too many requests — try again in {$exceeded->retryAfterMinutes()} minutes.";

            return response()->view($view, ['message' => $message], 429)
                ->header('Retry-After', (string) $exceeded->retryAfterSeconds);
        });

        // The response to a request that threw is built past the middleware
        // that opened it, so the id that finds the request's log lines is
        // stamped on it here rather than there.
        $exceptions->respond(function (Response $response, Throwable $error, Request $request): Response {
            $requestId = $request->attributes->get(LogRequestStory::REQUEST_ID_ATTRIBUTE);

            if (is_string($requestId)) {
                $response->headers->set(LogRequestStory::REQUEST_ID_HEADER, $requestId);
            }

            return $response;
        });
    })->create();
