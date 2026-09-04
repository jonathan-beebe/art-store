<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Config;
use Symfony\Component\Finder\Finder;

/**
 * A rendered page carries a `<script>` the production CSP (no
 * `script-src`, so inline script falls back to `default-src 'self'`)
 * would block, when the tag has no `src` — the CSP allows a same-origin
 * `src`, never an inline body.
 */
function pageCarriesAnInlineScript(string $html): bool
{
    return preg_match('/<script(?![^>]*\bsrc=)[^>]*>/', $html) === 1;
}

it('carries the security headers on a storefront page', function (): void {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('sends the byte-for-byte production CSP with app.debug off', function (): void {
    Config::set('app.debug', false);

    $this->get('/')->assertHeader(
        'Content-Security-Policy',
        "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'",
    );
});

it('widens the CSP for the framework debug page with app.debug on', function (): void {
    Config::set('app.debug', true);

    $this->get('/')->assertHeader(
        'Content-Security-Policy',
        "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; script-src 'self' 'unsafe-inline'",
    );
});

it('lets only this origin frame a design-system specimen', function (): void {
    Config::set('app.debug', false);

    $this->get('/design-system/specimens/browse-sheet')->assertHeader(
        'Content-Security-Policy',
        "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'self'",
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

it('IMPRV-030 has no blade view anywhere with a script tag lacking src', function (): void {
    $offenders = [];

    $finder = (new Finder)->in(resource_path('views'))->name('*.blade.php')->files();

    foreach ($finder as $file) {
        if (pageCarriesAnInlineScript($file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('IMPRV-030 carries no unsafe-inline for scripts in the production CSP', function (): void {
    Config::set('app.debug', false);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    // style-src's own 'unsafe-inline' (the theme's inline design tokens,
    // SecurityHeaders' own doc comment) is the only occurrence allowed;
    // production sets no script-src at all, so a second occurrence would
    // mean one crept in.
    expect(substr_count($csp, "'unsafe-inline'"))->toBe(1)
        ->and($csp)->not->toContain('script-src');
});
