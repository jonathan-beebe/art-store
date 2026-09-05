<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\CommerceTestCase;

/**
 * @return array<int, Route>
 */
$adminGuardedRoutes = function (): array {
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('auth.admin', $route->gatherMiddleware(), true))
        ->values()
        ->all();
};

/**
 * @return array{0: string, 1: string}
 */
$requestFor = function (Route $route): array {
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
};

/**
 * Every route behind the `auth.admin` middleware turns away a signed-out
 * visitor, a signed-in seller, and a signed-in customer alike, sending each
 * to admin sign-in rather than the page. Derived from the route table
 * rather than one test per controller, so a new admin-guarded route is
 * covered automatically.
 */
it('sends every actor to admin sign-in, not the page, for every admin-guarded route', function (Closure $actor) use ($adminGuardedRoutes, $requestFor): void {
    $routes = $adminGuardedRoutes();

    expect($routes)->not->toBeEmpty();

    /** @var array{0: Authenticatable, 1: string}|null $party */
    $party = $actor($this);
    if ($party !== null) {
        [$user, $guard] = $party;
        $this->actingAs($user, $guard);
    }

    foreach ($routes as $route) {
        [$method, $uri] = $requestFor($route);

        $this->call($method, $uri)->assertRedirect(route('auth.admin.login'));
    }
})->with([
    'guest' => [fn (CommerceTestCase $test): ?array => null],
    'seller' => [fn (CommerceTestCase $test): array => [$test->seller(), 'seller']],
    'customer' => [fn (CommerceTestCase $test): array => [$test->verifiedCustomer(), 'customer']],
]);
