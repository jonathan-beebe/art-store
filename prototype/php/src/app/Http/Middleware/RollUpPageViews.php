<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Analytics\Analytics;
use App\Domain\Analytics\PageViewCountability;
use App\Domain\Analytics\PageViewSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts page views rather than logging them: one row per site, route
 * pattern, and day, so `/admin/stats` reads traffic without a table that
 * grows per hit ({@see \App\Models\PageViewCount}).
 *
 * Registered once at the root of the global middleware stack, because a
 * middleware added there runs for every site, and the site a hit belongs to
 * is read back off the route's own pattern. It is terminable because the
 * route and status a hit counts against are only known once the response is
 * built. {@see Analytics} only buffers the count here; the write happens
 * whenever the buffer flushes.
 */
final readonly class RollUpPageViews
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $route = $request->route();

        // A request that matched no route has no pattern to count against.
        if ($route === null) {
            return;
        }

        $contentType = $response->headers->get('Content-Type');

        if (! PageViewCountability::isCountable($request->method(), $response->getStatusCode(), $contentType)) {
            return;
        }

        $pattern = $this->pattern($route->uri());

        app(Analytics::class)->recordPageView(PageViewSite::fromRoutePattern($pattern), $pattern, now()->toDateTimeImmutable());
    }

    /**
     * `Route::uri()` carries no leading slash except for the root route
     * itself, where it already is one; the pattern stored is the one
     * `PageViewSite::fromRoutePattern` and every reader of the table expect.
     */
    private function pattern(string $uri): string
    {
        return $uri === '/' ? $uri : '/'.$uri;
    }
}
