---
id: FEAT-016
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-016: Live unread badge over eventstream sse

## Problem
The unread badge in all three layouts is computed once per request by a view composer, so a message that arrives while a page is open is invisible until the next navigation. Every prototype in this comparison claims "no JavaScript required" and this one has no `<script>` tag anywhere (`docs/review.md`, `README.md`), which makes the claim true and the badge stale.

## Goal
The unread badge updates while a page sits open, and every page still works with JavaScript off.

## Outcome
- Each of the three sites serves an events endpoint that streams the signed-in actor's unread message count and sends a new value only when the number changes.
- With a thread page open on one site and a message posted from another, the badge changes without a reload.
- The stream ends on its own after a bounded time and the browser reconnects; a closed tab leaves nothing running.
- The endpoint on each site is reachable only by that site's actor, and the number it sends is only ever that actor's.
- With JavaScript disabled every page renders the same count it renders today and every action still works.
- The cost of a held connection — one server worker for its lifetime, one count query per tick — is stated in a comment and in the docs.
- `README.md` and `docs/review.md` say what JavaScript is in the tree and what still holds without it.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
A live badge is the one capability the reviewers will look for that a form-POST prototype cannot fake, and Laravel ships the mechanism first-party. Doing it without a framework, a bundler, or a broadcaster is the demonstration.

## Discovery notes
- `docs/messaging.md` § "The live badge" is the design and states both costs.
- `response()->eventStream(Closure $callback, array $headers = [], StreamedEvent|string|null $endStreamWith = '</stream>')` is first-party in Laravel 13 (`Illuminate\Routing\ResponseFactory`). It iterates the generator, checks `connection_aborted()` between yields, and writes `event:` / `data:` frames. `Illuminate\Http\StreamedEvent` names the event.
- Routes: `GET /seller/events` (`seller.events`) inside `auth.seller`, `GET /events` (`shop.events`) inside `customer.identity`, `GET /admin/events` (`admin.events`) inside `auth.admin`. The guard is the whole authorization story — the generator reads the actor the request resolved and nothing else, so no actor id can arrive from the client.
- The generator re-runs the same `Message` unread scope FEAT-011 landed and yields only on a change. The tick interval and the stream's bounded lifetime are yours **(decided at build time)**; both belong in one place, named, not scattered as literals.
- `app/Domain` reads no clock and `Controller::now()` is the one producer. A deadline computed from `now()` at request time, compared against a clock the generator can read, is the shape that keeps `tests/Arch.php` green — decide where the waiting lives.
- `artisan serve` is PHP's built-in server: one request per worker. Add `PHP_CLI_SERVER_WORKERS` to `docker-compose.yml` or a held stream starves every other request. Say how many and why in the compose file.
- The session driver is `database` (`config/session.php`), so a held request does not block the same browser behind a session file lock. Confirm that still holds before shipping.
- Client side: one `<script defer>` per layout over roughly 20 lines in `resources/js` or `public/`, no bundler entry beyond what Vite already builds, no dependency. Return early when `EventSource` is absent.
- Testing a stream is the hard part. `$this->get()` buffers a `StreamedResponse`; `streamedContent()` runs the callback to completion, so a stream that never ends hangs the suite. Drive the generator directly for the "only yields on change" and "stops at the deadline" cases and use the HTTP test for the content type and the guard. Budget time for this.
- `tests/Arch.php` and `tests/SidecarsTest.php` both apply to the new classes; the JavaScript file has no sidecar and needs no exception, but the controller and the generator do.
- Risk: this is the first `<script>` tag in the tree. `docs/review.md`'s "No JavaScript required" row and `README.md`'s "There is no JavaScript bundle and no `<script>` tag in any view" both become false the moment it lands. Rewrite them here rather than leaving FEAT-017 to discover it.

## Related work
- FEAT-012, FEAT-013, FEAT-014 (the composers and layouts this extends). Node's FEAT-016 solved the same problem with an in-process emitter — read it before choosing the shape here.

## Working

### Decisions

- **`App\Support\UnreadCountStream`** (final class, private constructor, two named
  constants) is the generator: `TICK_SECONDS = 2`, `LIFETIME_SECONDS = 25`.
  `forActor(Seller|Customer|Admin $actor, DateTimeImmutable $deadline): Generator`
  loops `while (now()->lt($deadline))`, re-runs `Message::query()->unreadInInboxOf($actor)`
  (the same scope FEAT-013 landed and the three layout composers already read — no
  second definition), yields a `StreamedEvent('unread', $count)` only when the count
  differs from the last one sent, and waits with `Sleep::for(self::TICK_SECONDS)->seconds()`.
  It lives in `app/Support` (adapters, not `app/Domain`) because it reads Eloquent and
  the clock — `tests/Arch.php`'s "the domain core stays pure" rule only reaches
  `App\Domain`, so this is architecturally the shell, and the deadline it compares
  against was itself computed from `Controller::now()` one layer up.
- Three thin invokable controllers (`Seller\EventsController`, `Shop\EventsController`,
  `Admin\EventsController`) each: resolve the actor from their site's own guard
  (`$this->seller()` / `$this->visitor()` / `$this->admin()`), compute the deadline
  from `$this->now()`, and return `response()->eventStream(fn(): Generator =>
  UnreadCountStream::forActor($actor, $deadline), endStreamWith: null)`. No route
  parameter carries an actor id, so no client input can select whose count a stream
  reads — the guard is the whole authorization story, as the design called for.
- Routes: `GET /seller/events` (`seller.events`, inside `auth.seller`), `GET /events`
  (`shop.events`, inside the storefront's `customer.identity` group, same place as
  `shop.messages.index` — not nested under `auth.customer`, since a stream is owed to
  an anonymous visitor's own count too), `GET /admin/events` (`admin.events`, inside
  `auth.admin`).
- `docker-compose.yml`: `PHP_CLI_SERVER_WORKERS: "5"` plus `--no-reload` on the
  `artisan serve` command — Laravel's `ServeCommand` silently ignores the env var and
  falls back to one worker without that flag (read from
  `vendor/.../ServeCommand.php` before writing this). Comment states the number and
  why.
- Client: `src/public/live-badge.js` (~20 lines, no dependency), served as a plain
  static file since Vite's only input is `resources/css/app.css` and this needs no
  bundling. Each "Messages" nav link carries `data-live-badge="Messages"` and
  `data-events-url="{site}.events"` directly on the existing `<a>` — no wrapping
  `<span>` — so the server-rendered "Messages (N)" text that 8 existing tests already
  `assertSee()` is untouched; the script only rewrites `textContent` after an `unread`
  frame arrives, which never happens inside a PHPUnit run. `<script defer>` added once
  per layout, right before `</body>`.
- Tests: per the ticket's own guidance, `UnreadCountStreamTest` drives the generator
  directly — `Sleep::fake(syncWithCarbon: true)` plus `$this->freezeTime()` makes the
  deadline loop deterministic with no real waiting (each faked sleep advances Carbon's
  test clock by `TICK_SECONDS`, so the loop's own `while` condition eventually trips).
  Covers: first frame carries the actor's own count; no further frame while the count
  holds steady, and the generator stops exactly at the deadline (asserted via
  `Sleep::assertSleptTimes(ceil(LIFETIME_SECONDS / TICK_SECONDS))`); a new frame once
  the count changes mid-stream; two different actors read two different counts from
  the same call. Each `EventsControllerTest` only asserts `Content-Type:
  text/event-stream; charset=UTF-8` over `$this->get(...)` — never consumed via
  `streamedContent()`, since the response callback only runs when the body is
  consumed, so this stays fast and never risks a hang. Guard coverage for
  `seller.events`/`admin.events` comes largely from the existing
  `GuardedRoutesTest.php` files (route-table driven, picks up any new
  `auth.seller`/`auth.admin` route automatically); `admin.events` also gets an
  explicit guest-redirect test since there is no `Admin/GuardedRoutesTest.php`.
- `README.md`, `docs/review.md`, and `docs/messaging.md`'s "The live badge" section
  (which had left the tick interval and deadline as "(decided at build time)") are
  rewritten to state what actually shipped, including the constant values and the
  `--no-reload` requirement. `docs/architecture.md`'s "no JavaScript required" line
  was left alone — it is still literally true (JavaScript is optional, not required)
  and the Node prototype's equivalent ticket left its architecture doc for a later
  ticket too.

### Verified live

`make up`, restarted to pick up the compose change. Full admin login walk over curl
(cookie jar): `GET /admin/login` → `POST /admin/login` with the seeded
`admin@example.com` → the session-flashed magic link on the redirected-back login
page → `GET` the magic link → signed in. The dashboard's rendered HTML carried
`data-live-badge="Messages" data-events-url=".../admin/events"` on the Messages link
and `Messages (2)`, plus `<script defer src=".../live-badge.js">` before `</body>`.
`GET /live-badge.js` served the file directly (`Content-Type: application/javascript`).
`GET /admin/events` with the same cookie jar answered
`Content-Type: text/event-stream; charset=utf-8` and its first frame was
`event: unread\ndata: 2` — the same number the dashboard had just rendered.
`GET /events` with no cookies at all (anonymous storefront visitor) answered its own
stream, first frame `data: 0`, and set a fresh `customer_id` cookie — confirming a
stream never needs a signed-in actor on the storefront and never leaks another
visitor's count.

### Found, not fixed

Nothing found outside this ticket's scope.

### Verification

`make check`: 1107 tests passed, 2491 assertions, 100.0% line coverage, 0 PHPStan
errors, Pint clean. (Baseline was 1099 tests / 2467 assertions — this ticket added 8
tests across 4 sidecars: `UnreadCountStreamTest` (4), `Seller\EventsControllerTest` (1),
`Shop\EventsControllerTest` (1), `Admin\EventsControllerTest` (2).)

## Review

Walked the whole live path over curl against the running container. Signed the
seeded `admin@example.com` in through `POST /admin/login` → the flashed magic
link → `/admin`, whose nav rendered `Messages (2)` with
`data-live-badge="Messages" data-events-url=".../admin/events"` and the
`<script defer src=".../live-badge.js">`. Held `/admin/events` open, then posted
a message into the seeded `admin_seller` thread from the seller side through
`PostMessage`. The open stream emitted a second frame — `event: unread` /
`data: 3` — one second after the post, on a connection whose first frame had
been `data: 2`. The badge moves, not just its first paint.

The rest of the probes: the stream closes on its own at 26s (25s deadline plus
the tick it is inside); `/seller/events` and `/admin/events` with no cookie
redirect to their sites' logins and `/events` answers anonymously, minting a
`customer_id` cookie; the container runs one `php -S` master plus five workers
and served four concurrent streams while `/admin` and `/` still answered in
under 50ms. The four `Sleep::fake` cases each drive a distinct behaviour — the
first frame, the silence plus the deadline (via `assertSleptTimes(13)`), the
change mid-stream, and two actors reading two different counts.
`public/live-badge.js` re-renders `label + ' (' + count + ')'`, which is the
same string the three layouts render, and drops the parenthetical at zero, so a
live update and a reload agree.

Changed here: `docs/architecture.md` § "The clock" now names
`UnreadCountStream` as the third instant producer and says why a held stream
reads `now()` per tick (the previous text called `RunWeeklyPayouts` "the one
other producer"), plus the `Sleep::fake(syncWithCarbon: true)` note.
`docs/messaging.md` § "The live badge" gains the measured worker capacity, the
absent `retry:` hint, and the two costs below.

### Review — found, not fixed

- A closed tab does not free its worker at once. `eventStream()` checks
  `connection_aborted()` between yields and this generator yields only on a
  change, so an abandoned stream keeps polling; measured, five aborted streams
  starved the next page load for about five seconds before the workers came
  back. Bounded by `LIFETIME_SECONDS` in the worst case. Fixing it means
  yielding a keepalive every tick, which trades the "only on change" property
  away — the prototype's scale does not pay for that. Recorded in
  `docs/messaging.md`.
- A cookieless client of the storefront's `/events` mints a `customers` row per
  request, so a crawler mints one per reconnect and holds a worker for each.
  This is the `customer.identity` middleware's existing shape — `GET /` with no
  cookie mints a row too (verified: three cookieless hits, three new rows,
  either route) — so `/events` opens no new hole, it only makes the existing
  one hold a worker while it does. Recorded in `docs/messaging.md`.

### Review verification

`make check`: 1107 tests passed, 2491 assertions, Pint clean, 0 PHPStan errors.
`pest --coverage --min=100`: 100.0% total (needs `-d memory_limit=1G`; the
container's 128M default exhausts under the coverage driver).
