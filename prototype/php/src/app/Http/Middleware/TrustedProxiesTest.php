<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Support\Env;

/**
 * bootstrap/app.php wires TRUSTED_PROXIES into the framework's TrustProxies
 * middleware with the forwarded ip, port, and proto headers. The proto is the
 * one a browser can see: behind a TLS-terminating proxy every generated URL —
 * the Vite stylesheet link included — must follow X-Forwarded-Proto, or the
 * page arrives over https referencing http assets and the browser blocks
 * them as mixed content.
 *
 * The middleware config is read once while the application boots, so the
 * trusted case sets the variable and boots a fresh application; the
 * in-memory database that boot abandons is migrated back by hand.
 */

/** The absolute URL the storefront's stylesheet link carries. */
function stylesheetUrl(string|false $content): string
{
    preg_match('/href="([^"]*\/build\/assets\/[^"]*\.css[^"]*)"/', (string) $content, $matches);

    return $matches[1] ?? '';
}

it('generates https asset urls when a trusted proxy forwards the scheme', function (): void {
    Env::getRepository()->set('TRUSTED_PROXIES', '*');

    try {
        $this->refreshApplication();
        $this->artisan('migrate');

        $response = $this->get('/', ['X-Forwarded-Proto' => 'https']);

        $response->assertOk();
        expect(stylesheetUrl($response->getContent()))->toStartWith('https://');
    } finally {
        Env::getRepository()->clear('TRUSTED_PROXIES');
    }
});

it('ignores a forwarded scheme when no proxy is trusted', function (): void {
    $response = $this->get('/', ['X-Forwarded-Proto' => 'https']);

    $response->assertOk();
    expect(stylesheetUrl($response->getContent()))->toStartWith('http://');
});
