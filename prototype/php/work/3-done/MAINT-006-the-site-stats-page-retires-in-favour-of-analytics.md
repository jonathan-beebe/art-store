---
id: MAINT-006
type: maintenance
status: resolved
created: 2026-09-02
---

# MAINT-006: the site stats page retires in favour of analytics

## Problem

The admin has two pages reading the same analytics store. `/admin/stats`
(app/Http/Controllers/Admin/StatsController.php,
resources/views/admin/stats/index.blade.php, nav label "Site stats") shows
page views by day for one week, page views by route pattern, and a platform
tally of listing events. `/admin/analytics` (FEAT-045) shows the same
numbers with a range, the range before, and a drill-in: page views are an
event row with daily bars, their breakdown by pattern is the `page.view`
event page, and the listing-event tally is the events table. The nav, the
dashboard's quick links, `docs/admin.md`, and `docs/alignment.md` §5 still
carry the old page.

## Goal

One analytics surface in the admin.

## Outcome

The nav and the dashboard link to Analytics and to nothing named Site
stats; a request to `/admin/stats` lands on `/admin/analytics` with a
permanent redirect so an old bookmark still works; the stats controller,
view, and the domain values only it used are gone, with their tests; every
number the stats page showed is still reachable on the analytics pages
(page views by day and by pattern, the event tally) and the tests that
locked those numbers move to the analytics pages if they are not already
there; `docs/alignment.md` §5 and §8, `docs/admin.md`, `docs/analytics.md`,
and the code comments that name `/admin/stats` say what replaced it; the
suite stays green.

## Why it matters

Two pages for one question means one of them drifts. The stats page was
the first reader of the store; the drill-in supersedes it, and every
future analytics change would otherwise be made twice.

## Discovery notes

- References: `routes/admin.php:65`, the admin layout's nav entry
  (`resources/views/components/layouts/admin.blade.php:122`), the
  dashboard's two links (`resources/views/admin/dashboard.blade.php:82,184`),
  comments in `RollUpPageViews`, `PageViewCountability`,
  `ListingEventTally`, and `AnalyticsReport::platformCountsByName()`.
- `App\Domain\Reports\ListingEventTally` and `ListingEventCount` may have
  no reader once the controller goes; `AnalyticsReport::platformCountsByName()`
  may still serve the analytics event totals — check before deleting.
- The alignment contract lists `/admin/stats` in §5 for every prototype.
  Node and Rails keep theirs until they ship a drill-in; the §5 row can
  read "PHP: redirects to `/admin/analytics`" with a §8 entry, the way
  earlier per-prototype divergences were recorded.
- The docs/review.md test matrix names `Admin\StatsControllerTest`; the
  analytics controller tests take its place in that row.

## Related work

- FEAT-045 — the analytics drill-in this page retires into
- FEAT-023 — the admin dashboard and stats page

## Working

Commits (`php/retire-stats`, off `php/analytics-admin`):

- `d06cd71b` — ticket defined and started
- `46db4834` — the analytics pages lock every number the stats page showed
  (`AnalyticsControllerTest`, `EventControllerTest`)
- `61598379` — `/admin/stats` redirects to analytics and the stats page is
  gone
- `2540949b` — docs updated

Deleted: `StatsController` and its view, `App\Domain\Reports\ListingEventTally`
and `ListingEventCount` (with their tests), `AnalyticsReport::platformCountsByName()`
(no reader left once the controller went) and its dedicated test.

Kept: `RollUpPageViews` and `PageViewCountability` — both still write and
gate the page-view rollup the analytics pages read; their comments now
name the admin analytics pages instead of `/admin/stats`.

`/admin/stats` is now `Route::permanentRedirect('stats', '/admin/analytics')`
inside the `admin.` group, so it keeps the `auth.admin` guard and needs no
route name. The redirect destination has to be a plain path, not a
`route()` call — a named route registered earlier in the same file isn't
resolvable by `route()` yet at registration time, since Laravel only
refreshes the route collection's name lookup once the whole file has
loaded; calling `route('admin.analytics.index')` there throws
`RouteNotFoundException`.

Three tests that used `AnalyticsReport::platformCountsByName()` only to
confirm a `listing.unfavorite` analytics event landed
(`ToggleFavoriteTest`, `FavoriteControllerTest`, `DatabaseSeederTest`) now
query `analytics_events` directly, the pattern already used elsewhere in
the suite.

The nav's "Site stats" entry, the dashboard's "Page views this week" card,
and its quick-link list all point at `/admin/analytics` now; the drawer
and below-`sm` dashboard tests were updated to match.

`make precommit`: Pint clean, PHPStan clean, 3710 tests passed (10662
assertions).

Open: none.
