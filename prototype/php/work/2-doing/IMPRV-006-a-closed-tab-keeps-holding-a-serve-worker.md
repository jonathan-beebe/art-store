---
id: IMPRV-006
type: improvement
status: open
created: 2026-08-24
---

# IMPRV-006: A closed tab keeps holding a serve worker

## Problem
`UnreadCountStream::forActor()` yields a frame only when the count moves.
Laravel's `ResponseFactory::eventStream()` checks `connection_aborted()`
once per yielded frame, and PHP only learns a connection is gone by failing
to write to it. So after the first frame a stream writes nothing more for
its remaining 25 seconds, never writes, never learns the browser left, and
holds one of the five `php artisan serve` workers for the whole lifetime.

Every rendered page opens a stream (`public/live-badge.js`), so ordinary
navigation leaves a trail of streams whose tabs are already gone. Measured
(RSRCH-001 M8): twelve streams opened and every client killed after three
seconds still stalled the next page load for **49.4 s**. Eight concurrent
streams already push a page load from 0.06 s to 0.22 s.

This is what the site feels like when it "bogs down": not CPU, but every
worker parked on a connection nobody is listening to.

## Goal
A worker is released within one tick of the browser going away.

## Outcome
RSRCH-001 M8 — twelve streams opened, all clients killed after 3 s, then a
`GET /` — answers in **under 1 s**, against the 49.4 s baseline. The 8-stream
and 12-stream rows of the M8 table both come back under 1 s. Idle container
CPU with three live streams (RSRCH-001 M4) stays at or under its 5.6 %
baseline. The badge still shows a correct count on the page and still
updates live while a tab sits open.

## Why it matters
It is the only measured cost in the prototype that a person actually
notices, and it grows with normal use rather than with load.

## Discovery notes
The generator yields on every tick rather than only on a change. The client
is already idempotent — `live-badge.js` sets `textContent` from whatever
`event.data` carries, so a repeated identical count is a no-op in the
browser — and `docs/alignment.md` names only "badge-only is the shared CX",
never a rule about when a frame is emitted. That makes the wire change
contract-clean.

Cost of the change: 13 frames per 25-second stream instead of 1, each a
`data: <int>`. The `count` query per tick is unchanged — it already ran
every tick to decide whether to yield.

Keep `$lastSent` if it still earns its place; the point is that the
generator must reach `yield` on every tick so `eventStream`'s
`connection_aborted()` check and the write that feeds it are reached.

Verify the released-worker claim by measurement, not by reading: re-run M8
and report the number. If M8 still stalls after the change, say so rather
than shipping it — a second lever exists (`PHP_CLI_SERVER_WORKERS` in
`docker-compose.yml`, currently 5), but raise it only if the measurement
says the abort fix alone is not enough, and record the before/after either
way.

The class docblock and `docker-compose.yml`'s comment both describe the
current only-on-change behaviour and the worker cost. Both describe current
code, so both are part of the change.

## Related work
- FEAT-016 (live unread badge over EventStream SSE)
- RSRCH-001 (M8)
