<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Every route behind the `auth.customer` middleware sends a signed-out
 * visitor to customer sign-in. Derived from the route table rather than one
 * test per controller, so a new customer-guarded route is covered automatically.
 */
it('sends a signed-out visitor to customer sign-in for every customer-guarded route', function (): void {
    $routes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('auth.customer', $route->gatherMiddleware(), true));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        /** @var list<string> $parameterNames */
        $parameterNames = $route->parameterNames();

        /** @var list<string> $methods */
        $methods = $route->methods();

        $uri = $route->uri();
        foreach ($parameterNames as $parameter) {
            $uri = (string) preg_replace('/\{'.preg_quote($parameter, '/').'\??\}/', '1', $uri);
        }
        $method = collect($methods)->firstOrFail(fn (string $method): bool => $method !== 'HEAD');

        $response = $this->call($method, '/'.ltrim($uri, '/'));

        $response->assertRedirect(route('auth.customer.login'));
    }
});
