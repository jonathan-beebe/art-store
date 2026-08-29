<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PlaceholderImage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security headers on every response the `web` group answers with.
 * `img-src` carries `data:` beyond `'self'`: {@see PlaceholderImage}
 * renders a listing with no photograph as an inline `data:image/svg+xml`
 * `<img src>`. `style-src` carries `'unsafe-inline'`: the theme's design
 * tokens reach the page as an inline `<style>` block (`<x-theme-css />`),
 * and the category pickers paint listing covers through `style`
 * attributes — scripts stay locked to `'self'` outside debug. Fonts are
 * self-hosted under public/fonts, so no font origin needs naming.
 *
 * Framing is refused everywhere (`frame-ancestors 'none'`) except the
 * design-system specimen routes, which the design-system page frames in
 * its own phone-width iframes — those answer `'self'`, so only this
 * origin can embed them.
 *
 * HSTS is production-only: `make up`'s local server has no certificate for
 * a browser to keep pinning past, and it does not stop being local by
 * skipping the header.
 *
 * With `app.debug` on, an unhandled exception renders the framework's own
 * debug page, whose inline `<script>` needs `'unsafe-inline'` — the
 * widened policy is debug-only and never reaches a production response.
 */
final readonly class SecurityHeaders
{
    private const string CSP = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; form-action 'self'";

    private const string DEBUG_CSP_ADDITIONS = "; script-src 'self' 'unsafe-inline'";

    private const string REFERRER_POLICY = 'strict-origin-when-cross-origin';

    // Two years, the floor browsers preload HSTS lists ask for, with
    // subdomains covered since every site here shares one origin's cookies.
    private const string HSTS = 'max-age=63072000; includeSubDomains';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $frameAncestors = $request->routeIs('shop.design-system.specimen') ? "'self'" : "'none'";

        $response->headers->set(
            'Content-Security-Policy',
            self::CSP."; frame-ancestors {$frameAncestors}".(app()->hasDebugModeEnabled() ? self::DEBUG_CSP_ADDITIONS : ''),
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', self::REFERRER_POLICY);

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
