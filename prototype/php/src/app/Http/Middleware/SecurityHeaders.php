<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PlaceholderImage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/alignment.md §"Security headers", on every response the `web` group
 * answers with. `img-src` carries `data:` beyond the contract's three named
 * directives: {@see PlaceholderImage} renders a listing with no photograph
 * as an inline `data:image/svg+xml` `<img src>`, and `default-src 'self'`
 * alone would block it.
 *
 * HSTS is production-only: `make up`'s local server has no certificate for
 * a browser to keep pinning past, and it does not stop being local by
 * skipping the header.
 */
final readonly class SecurityHeaders
{
    private const string CSP = "default-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'";

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

        $response->headers->set('Content-Security-Policy', self::CSP);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', self::REFERRER_POLICY);

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
