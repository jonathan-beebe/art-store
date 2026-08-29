<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Config;
use RuntimeException;

it('renders the branded 404 page for a route that matches nothing', function (): void {
    $response = $this->get('/nothing-is-here');

    $response->assertNotFound();
    $response->assertSee('Page not found');
});

it('renders the branded 419 page for an expired form', function (): void {
    Config::set('app.debug', false);

    $response = app(ExceptionHandler::class)->render(Request::create('/'), new TokenMismatchException);

    expect($response->getStatusCode())->toBe(419);
    expect((string) $response->getContent())
        ->toContain('Page expired')
        ->toContain('This form took too long and expired. Go back and resubmit it.');
});

it('renders the branded 500 page for an uncaught exception outside debug', function (): void {
    Config::set('app.debug', false);

    $response = app(ExceptionHandler::class)->render(Request::create('/'), new RuntimeException('a secret internal detail'));
    $content = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(500);
    expect($content)->toContain('Something went wrong');
    expect($content)->not->toContain('RuntimeException');
    expect($content)->not->toContain('a secret internal detail');
});

// The framework's debug page embeds a syntax-highlighted source excerpt of
// every frame in the stack, including this test method's own file — so
// asserting the *absence* of the branded page's exact copy here is
// self-referential and unreliable (that copy sits a few lines away in this
// same file, in the test above, and would show up in the excerpt on a
// coincidence of proximity rather than a real regression). Presence of the
// raw exception message, plus the debug bundle's size, distinguish it from
// the branded page without that trap.
it('shows the framework debug page instead of the branded one when app.debug is on', function (): void {
    Config::set('app.debug', true);

    $response = app(ExceptionHandler::class)->render(Request::create('/'), new RuntimeException('a secret internal detail'));

    expect($response->getStatusCode())->toBe(500);
    expect((string) $response->getContent())->toContain('a secret internal detail');
    expect(strlen((string) $response->getContent()))->toBeGreaterThan(100_000);
});
