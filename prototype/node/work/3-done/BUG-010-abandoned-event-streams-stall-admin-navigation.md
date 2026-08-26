---
id: BUG-010
type: bug
status: resolved
created: 2026-08-26
---

# BUG-010: abandoned event streams stall admin navigation

## Problem

The unread-badge `EventSource` (`public/app.js`) is never closed; when a
page is left, releasing its connection is up to the browser, and Chrome
releases it lazily. Observed 2026-08-26 UTC in the dev log store while
filtering `/admin/logs`: streams from already-left pages survived their
navigation by 30–65 seconds, four ran concurrently from one browser
session, and each batch aborted (`data.disconnected: true`) at the exact
millisecond a later navigation was served. The dev server speaks HTTP/1.1,
where Chrome's per-host budget is six connections, so the held streams
queue the next navigation — a filter submit — behind them. The server
answered every `GET /admin/logs` in 24–56ms; the queueing in front of it is
what reads as a slow page.

## Goal

Leaving a page releases its event stream's connection at navigation time.

## Outcome

`app.js` closes its `EventSource` on `pagehide`, so the connection slot
frees when the navigation starts instead of when the browser gets around to
it. Filter submits on `/admin/logs` land in the server's measured tens of
milliseconds. The progressive-enhancement contract holds: every page still
works with the file absent, blocked, or unsupported.

## Why it matters

The log viewer is a page a founder refreshes and re-filters in quick
succession — the exact usage that piles up held streams and turns a 30ms
page into a multi-second wait. The badge stream must never cost the
navigation it decorates.

## Discovery notes

Advisory: one `pagehide` listener calling `source.close()`. `pagehide` also
fires on bfcache entry, so a page restored from bfcache holds a closed
stream and its badge goes static until the next full load — acceptable for
a badge; reconnecting on `pageshow` with `event.persisted` is the upgrade
if it ever matters. The server side already closes the story correctly on
abort (BUG-009); this is purely the client half. PHP and Rails share
`app.js`-equivalent stream clients and likely the same gap — follow-up
outside this ticket.

## Working

- 2026-08-26 — measured before the fix, from the log store: `GET
  /admin/logs` served in 24–56ms while four `/admin/events` streams from
  left pages ran concurrently (30–65s past their navigation), aborting in a
  burst at the exact millisecond a later navigation was served.
- Fix: `public/app.js` adds one `pagehide` listener calling
  `source.close()`.
- Verified after the fix by driving Chrome through four storefront
  navigations and reading the store: exactly one stream in flight at any
  moment, each closing with `disconnected: true` 31–48ms after the next
  page's `will` line. `make check` green, 2166 tests, coverage
  99.40/95.66/99.55.
- Side observation from the verification run: a dev-server restart leaves
  the browser holding half-dead sockets to the old container (no FIN
  through the docker proxy), which starves the same per-host budget until
  TCP timeout; reloading the tab clears it. Recorded here as context, out
  of scope.

## Related work

- 4ceb7e9 (BUG-009) — the server-side close of the same abort
- FEAT-021 — the log viewer whose filter round-trips surfaced the stall,
  and whose store measured it
