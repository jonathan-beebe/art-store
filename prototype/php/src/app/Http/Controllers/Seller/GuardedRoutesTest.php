<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Every route behind the `auth.seller` middleware sends a signed-out visitor
 * to seller sign-in. Derived from the route table rather than one test per
 * controller, so a new seller-guarded route is covered automatically.
 */
it('sends a signed-out visitor to seller sign-in for every seller-guarded route', function (): void {
    $routes = collect(RouteFacade::getRoutes())
        ->filter(fn (Route $route): bool => in_array('auth.seller', $route->gatherMiddleware(), true));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $uri = $route->uri();
        foreach ($route->parameterNames() as $parameter) {
            $uri = preg_replace('/\{'.preg_quote($parameter, '/').'\??\}/', '1', (string) $uri);
        }
        $method = collect($route->methods())->first(fn (string $method): bool => $method !== 'HEAD');

        $response = $this->call($method, '/'.ltrim((string) $uri, '/'));

        $response->assertRedirect(route('auth.seller.login'));
    }
});
