---
id: IMPRV-020
type: improvement
status: resolved
created: 2026-08-31
---

# IMPRV-020: the request story closes when a stream actually ends

## Problem

The `http.request` story's `did` line is written by
`LogRequestStory::handle()` (`app/Http/Middleware/LogRequestStory.php:100-103`)
as soon as `$next($request)` returns — for the three SSE endpoints
(`app/Http/Controllers/{Shop,Seller,Admin}/EventsController.php`, streaming
via `response()->eventStream()` from `app/Support/UnreadCountStream.php`)
that is before the stream body runs. The logged `duration_ms` reflects setup
time rather than how long the stream was held (up to
`UnreadCountStream::LIFETIME_SECONDS` = 25s), `data.db` misses the one count
query per 2-second tick, and a client abandoning the stream mid-lifetime is
invisible. alignment.md §2.2 requires an abandoned request still close with
`did` carrying the streaming status, the duration since its `will`, and
`data.disconnected: true`.

## Goal

The request story tells the truth about streams — how long they were held,
what they cost, and whether the client left early.

## Outcome

An SSE request that runs to its deadline logs one `did` whose `duration_ms`
covers the full held stream and whose `data.db` covers the tick queries; one
abandoned mid-stream logs one `did` additionally carrying
`data.disconnected: true`; every request story, streamed or not, closes
exactly once, and non-streamed requests' lines are unchanged.

## Why it matters

The log viewer cannot tell a 25-second held worker from an instant response —
the blind spot behind "held SSE streams make pages feel slow while
`duration_ms` reads fast." Operators sizing `PHP_CLI_SERVER_WORKERS`
(docker-compose.yml sizes them around stream cost) need held-time and
abandonment facts, and §2.2 conformance keeps the three prototypes
comparable.

## Discovery notes

Advisory.

- Reporter constraint: no hand-rolled streaming loop — adjust how the app
  hooks Laravel's plumbing; simplest thing that yields accurate logging.
- Laravel middleware can be terminable: `terminate()` runs after
  `$response->send()` completes, which for a `StreamedResponse` is after the
  stream loop has ended (deadline reached, or write failed on abandon) —
  deferring the story's `did` to `terminate()` only when the response is a
  `StreamedResponse` looks like the plumbing-shaped seam, keeping `handle()`'s
  current line for ordinary responses.
- `eventStream()`'s own loop already breaks on `connection_aborted()`; at
  `terminate()` time `connection_aborted()` (or deadline vs. clock) can
  distinguish abandonment.
- Watch: `Story`/`DbActivity` state must survive until `terminate()`
  (`Story::forget()` runs at the next request's start; one cli-server worker
  handles one request at a time).
- Per the dev-environment notes, curl the live server after logging changes —
  cli-server SAPI quirks make sidecar tests alone insufficient verification
  here.

## Related work

- IMPRV-017 — `data.db` on request lines
- IMPRV-018 / commit c29625d — tests read those lines
- Commit 69d5297 — freed SSE workers
- Commits 5c18926..769f36f — closed the other §2.2 gaps; filed this one as
  future work

## Working

- 2026-08-31 — re-validated: `LogRequestStory::handle()` still writes `did`
  immediately after `$next()`; the three EventsControllers still stream via
  `response()->eventStream()`. The issue applies.
- Design: add `terminate(Request, Response)` to `LogRequestStory`. `handle()`
  keeps its `did` for ordinary responses; for a `StreamedResponse` it stashes
  the open `Story` on the request attributes (Laravel re-resolves the
  middleware from the container for `terminate()`, so instance state does not
  survive; the request instance does). `terminate()` writes the same `did`
  shape with `DbActivity::snapshot()` taken after the stream ended, plus
  `data.disconnected: true` when `connection_aborted()` says the client left.
  `Story::forget()`/`DbActivity::reset()` run at the next request's `handle()`,
  after `terminate()`, in both the cli-server and the test kernel — no change
  needed there.
- Verification: sidecar tests (unit-level handle→sendContent→terminate
  ordering, feature-level `/events`, disconnected seam) plus live-server curl
  of `/events` for the full 25s lifetime and a 5s abandon, per the
  dev-environment notes.
- 2026-08-31 — delivered as designed, with one addition the design missed:
  `ignore_user_abort(true)` in the `StreamedResponse` branch. Without it PHP
  ends the script on the first failed write after a client disconnect and
  `terminate()` never runs, so the abandoned case logged no `did` at all
  (found in live verification; the stream loop still exits within one tick
  because `eventStream()` checks `connection_aborted()` per frame, with the
  25s deadline as backstop).
- Tests: four new sidecar tests in `LogRequestStoryTest.php` — held-open
  through `handle()` and closed once at `terminate()` with the stream's cost
  on the line; exactly-once close for a streaming route; `disconnected: true`
  when the client left; the key absent when it did not. The
  `connection_aborted()` seam is a same-namespace function shadow, the
  pattern `FakeCardTest.php` already uses. Existing tests unchanged.
- Live evidence: full lifetime — `"GET /events 200"`, `duration_ms: 26137`,
  `db: {queries: 17, total_ms: 24.04}`, no `disconnected` key. Abandoned at
  5s — `duration_ms: 8043`, `db: {queries: 9}`, `disconnected: true`. One
  `will`/one `did` per request in both cases.
- `make check` green (3209 tests, Pint, PHPStan clean, 100% coverage).
- Validation review: accept, no defects blocking or advisory. Confirmed
  exactly-once closure on every path (exception in `handle()` never stashes;
  `eventStream()` swallows callback throwables; the test kernel terminates
  without `send()`; attributes are per-request; the re-resolved middleware
  holds no instance state).
