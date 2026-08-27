<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Config;

it('carries the security headers on a storefront page', function (): void {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('sends the byte-for-byte production CSP with app.debug off', function (): void {
    Config::set('app.debug', false);

    $this->get('/')->assertHeader(
        'Content-Security-Policy',
        "default-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'",
    );
});

it('widens the CSP for the framework debug page with app.debug on', function (): void {
    Config::set('app.debug', true);

    $this->get('/')->assertHeader(
        'Content-Security-Policy',
        "default-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'",
    );
});

it('carries them on a seller portal page', function (): void {
    $this->get('/seller/login')
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('carries them on an admin site page', function (): void {
    $this->get('/admin/login')
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('carries them on a route that matches nothing', function (): void {
    $response = $this->get('/nothing-is-here');

    $response->assertNotFound()
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('leaves HSTS off outside production', function (): void {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});

it('carries HSTS in production', function (): void {
    $this->app->instance('env', 'production');

    $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
});
