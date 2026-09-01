# Stateless badge — exploration notes (php/stateless-badge)

Branch: `php/stateless-badge`. One-prototype exploration; `docs/alignment.md`
is untouched per the brief.

## Owner decision, 2026-08-31

Original brief: replace the held SSE unread-badge stream with 2s client-side
polling, so every request is a normal ~10ms round trip and no thread parks
for the life of an open tab.

Mid-task change order from the owner superseded that: remove live badge
updates entirely. No stream, no polling. The badge is the server-rendered
count only (`unreadMessageCount` from the three view composers), refreshed
on navigation like every other number in the layout. Rationale given:
polling and real-time badge counts are unnecessary complexity for what this
prototype needs to demonstrate.

Everything built for the polling design (three JSON count endpoints,
`live-badge.js` rewritten to `fetch` on an interval) was deleted before
being committed, once the change order arrived — no removal commits on top,
since nothing had been committed yet.

## What shipped

- `src/public/live-badge.js` deleted. No client-side script for the badge.
- The three `events` routes, `EventsController`s (Seller/Shop/Admin), and
  their tests deleted — no JSON count endpoint either; the owner was
  explicit that a poll endpoint is not the replacement.
- `App\Support\UnreadCountStream` and its test deleted. No behavior ported:
  the actor-scoping and no-leakage-between-actors cases it exercised are
  already covered by the layout composer tests (see below), which build
  conversations for two actors and assert each page shows only its own
  count.
- `data-live-badge`, `data-events-url`, `aria-live`, `aria-atomic` attributes
  removed from the badge anchors in the seller, shop, and admin layouts —
  those attributes existed to support a script that no longer runs.
- `LogRequestStory`'s streamed-response path removed: no more
  `StreamedResponse` branch in `handle()`, no `terminate()` method,
  `ignore_user_abort()` gone. A grep across `app/`, `resources/`, and
  `routes/` for `StreamedResponse`/`eventStream`/`StreamedEvent` turned up
  nothing else — this prototype streams no response anywhere now, so the
  whole mechanism (not just the badge's use of it) is dead code.
- `LogRequestStoryTest`: the four streamed-response lifecycle tests (the
  `connection_aborted()` override, the two `terminate()`-carries-`disconnected`
  tests, and the "closes exactly once for a route that streams" test)
  deleted along with the mechanism they covered.
- `RollUpPageViewsTest`'s "counts nothing for a response that is not HTML"
  fixture repointed from the deleted `/admin/events` to a synthetic inline
  JSON route (`Route::get('/json-test', ...)`), so the assertion stands on
  its own rather than on a feature that no longer exists.
- `PageViewCountability::isCountable()` needed no code change. It has no
  explicit `text/event-stream` branch to remove — `isHtml` is a positive
  `str_contains(..., 'text/html')` check, so a JSON response (or any other
  non-HTML content type) was always excluded by construction, not by a
  special case naming the stream's content type.
- `docker/Caddyfile`: the `num_threads`/`max_threads` pin dropped from
  16/40 to a modest 4/8 (`FRANKENPHP_NUM_THREADS`/`FRANKENPHP_MAX_THREADS`
  still override). Comment rewritten: every request is now a normal ~10ms
  round trip with nothing held open, so the pool sizes for concurrent-request
  throughput rather than for a fixed count of parked connections.
- `docker-compose.yml`'s dev stack: `PHP_CLI_SERVER_WORKERS` dropped from 16
  to 4, comment rewritten to match — `artisan serve`'s workers no longer
  need to cover a fixed count of held streams, only ordinary concurrent
  browsing.

## Alignment §2.2 divergence

`docs/alignment.md` §2.2 specifies that an abandoned streamed request still
closes with `did`, carrying `data.disconnected: true` when the client left
mid-response, and §7 decision 9 assumes a live-updating badge stream exists
per stack (Node and PHP close their `EventSource` on `pagehide`; Rails owed
the same). On this branch, PHP has no streamed response anywhere, so:

- `disconnected` has no in-app referent. `LogRequestStory` never sets it;
  the field simply never appears in a PHP log line. This is not a bug in
  the removed code, it is the removed code.
- §7 decision 9 (client releases its stream connection on `pagehide`) is
  moot for PHP — there is no connection to release.

What a contract successor would need to say if this became the shared
shape: the unread badge is a value the server already renders into the page
on every request (the view-composer count), not a live-updating widget: no
stream, no poll, no `EventSource`, no `disconnected` semantics to define.
The guarantee that replaces "the story closes exactly once even when the
client leaves mid-stream" is simply that every request is request/response
— it opens and closes in one pass, which the ordinary `will`/`did` pair
already covers with no special case. Node and Rails would need the same
call made explicitly (drop their stream) rather than inferring it from
PHP's silence on `disconnected`.

## Efficiency findings deferred (not done — larger than this branch's surface)

- `App\Logging\Admin\LogRowQuery::SHOP_EXCLUDED_PATH = '/events'` (and its
  fixture in `LogRowQueryTest`) implement docs/alignment.md §5's "shop
  bucket excludes the health-probe path and `/events`" rule. With the route
  gone, this exclusion is vestigial — no request will ever match it again —
  but it is harmless, and removing it means editing a rule the alignment
  contract still states for the other two prototypes. Left in place.
- `README.md`, `docs/messaging.md`, and `docs/review.md` describe the SSE
  badge in real depth (a "Deployment" paragraph sizing the FrankenPHP pool
  against held streams, a "Known gaps" entry on the per-tab SSE connection,
  `docs/messaging.md`'s "The live badge" section, `docs/review.md`'s
  design-choice write-up contrasting PHP's poll-driven stream with Node's
  push-driven one). All three are now stale prose describing a feature this
  branch removes. Rewriting them is a real documentation task on its own —
  out of scope for this exploration's file surface — so they were left
  untouched; a reader of this branch's diff should expect those docs to
  disagree with the code until that pass happens.
- `work/3-done/FEAT-016-*`, `IMPRV-006-*`, `IMPRV-019-*`,
  `IMPRV-020-*`, and `work/journal.md` are historical tickets/journal
  entries for the feature this branch removes. Left untouched as history,
  not live documentation.

## Verification

`COMPOSE_PROJECT_NAME=php-stateless-badge make check` (lint → assets →
tests) passes: 3204 tests, 9323 assertions, 0 failed, 100.0% coverage.
`make smoke` passes: 4 tests, 174 assertions.

`make up` then a live curl round confirmed the removal end to end: `/`
renders the "Messages" nav item with no `data-live-badge`, no
`data-events-url`, and no `live-badge.js` script tag — only
`configurator-autosubmit.js` remains. `GET /events`, `/seller/events`, and
`/admin/events` all answer 404. The `http.request` log lines for both the
page load and the 404s carry the plain one-`will`/one-`did` shape with
`duration_ms` and `data.db`, unchanged from before this branch.
