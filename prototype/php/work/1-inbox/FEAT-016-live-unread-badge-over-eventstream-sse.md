---
id: FEAT-016
type: feature
status: open
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
