---
id: IMPRV-020
type: improvement
status: open
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
