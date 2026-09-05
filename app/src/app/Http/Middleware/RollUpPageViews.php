<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsVisit;
use App\Analytics\RequestFacts;
use App\Domain\Analytics\PageViewCountability;
use App\Domain\Analytics\PageViewSite;
use App\Shop\CustomerIdentity;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts page views as one row per site, route pattern, and day, so the
 * admin analytics pages read traffic without a table that grows per hit
 * ({@see \App\Models\PageViewCount}). Also where a storefront session's
 * first-touch visit is captured ({@see AnalyticsVisit}) — this class
 * already computes the two facts that decide whether a request counts
 * (`PageViewCountability`) and which site it belongs to, so capturing here
 * reuses both. `NameRequestVisitor` runs before the response exists and
 * cannot know either.
 *
 * Registered once at the root of the global middleware stack, because a
 * middleware added there runs for every site, and the site a hit belongs to
 * is read back off the route's own pattern. It is terminable because the
 * route and status a hit counts against are only known once the response is
 * built. {@see Analytics} only buffers the count and the visit here; the
 * write happens whenever the buffer flushes.
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
        $site = PageViewSite::fromRoutePattern($pattern);
        $now = now()->toDateTimeImmutable();

        app(Analytics::class)->recordPageView($site, $pattern, $now);

        if ($site === PageViewSite::Shop) {
            $this->recordVisit($request, $now);
        }
    }

    /**
     * `AnalyticsVisit::fromRequest()` reads null when the request carries
     * no session, which never happens on a real storefront hit. Recording
     * runs on every countable storefront request, including every one
     * after the `sid` cookie was minted: `Analytics::recordVisit()` writes
     * `INSERT OR IGNORE` on the session id, so only the first request of a
     * session ever changes a row and every later one is a no-op write.
     */
    private function recordVisit(Request $request, DateTimeImmutable $now): void
    {
        $visit = AnalyticsVisit::fromRequest($request, RequestFacts::current(), CustomerIdentity::current()?->id, $now);

        if ($visit !== null) {
            app(Analytics::class)->recordVisit($visit);
        }
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
