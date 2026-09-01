<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Every route behind the `auth.admin` middleware turns away a signed-out
 * visitor, a signed-in seller, and a signed-in customer alike, sending each
 * to admin sign-in rather than the page. Derived from the route table
 * rather than one test per controller, so a new admin-guarded route is
 * covered automatically.
 */
it('sends a guest to admin sign-in for every admin-guarded route', function (): void {
    $routes = adminGuardedRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        [$method, $uri] = requestFor($route);

        $this->call($method, $uri)->assertRedirect(route('auth.admin.login'));
    }
});

it('sends a signed-in seller to admin sign-in, not the page, for every admin-guarded route', function (): void {
    $routes = adminGuardedRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        [$method, $uri] = requestFor($route);

        $this->actingAs($this->seller(), 'seller')->call($method, $uri)
            ->assertRedirect(route('auth.admin.login'));
    }
});

it('sends a signed-in customer to admin sign-in, not the page, for every admin-guarded route', function (): void {
    $routes = adminGuardedRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        [$method, $uri] = requestFor($route);

        $this->actingAs($this->verifiedCustomer(), 'customer')->call($method, $uri)
            ->assertRedirect(route('auth.admin.login'));
    }
});

/**
 * @return list<Route>
 */
function adminGuardedRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('auth.admin', $route->gatherMiddleware(), true))
        ->values()
        ->all();
}

/**
 * @return array{0: string, 1: string}
 */
function requestFor(Route $route): array
{
    /** @var list<string> $parameterNames */
    $parameterNames = $route->parameterNames();

    /** @var list<string> $methods */
    $methods = $route->methods();

    $uri = $route->uri();
    foreach ($parameterNames as $parameter) {
        $uri = (string) preg_replace('/\{'.preg_quote($parameter, '/').'\??\}/', '1', $uri);
    }
    $method = collect($methods)->firstOrFail(fn (string $method): bool => $method !== 'HEAD');

    return [$method, '/'.ltrim($uri, '/')];
}
