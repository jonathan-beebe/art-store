---
id: IMPRV-006
type: improvement
status: resolved
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

## Working

Two changes, both measured before they were kept.

**1. `UnreadCountStream::forActor()` yields on every tick.** The `$lastSent`
comparison is gone: each tick yields the count it just read. `eventStream`
writes every yielded frame and checks `connection_aborted()` once per frame,
and PHP learns a socket is dead only from a failed write, so a frame per
tick is what lets a stream notice its browser left. `live-badge.js` sets the
label from whatever the frame carries, so a repeated count is a no-op in the
browser. The class docblock and the three `EventsController` docblocks
describe that behaviour now.

**2. `PHP_CLI_SERVER_WORKERS` 5 → 16.** Measured, not assumed: the yield fix
alone took the M8 killed-client case from 48.5 s to 4.2–8.5 s, short of the
under-1 s outcome. What remains at five workers is queueing — twelve
connections against five workers means seven wait in the accept backlog, and
each abandoned one still costs a worker one tick to discover. Sixteen covers
M8's twelve streams with room for page requests. Cost: about 5 MiB of
container memory per worker (90.5 MiB at 5, 137–164 MiB at 16, against the
RSRCH-001 M4 baseline of 101.8 MiB).

### M8 — `GET /` with N streams on `/events`

Each cell is `%{time_total}` for the page load, 35 s of settle before each
run. The two middle columns isolate the levers; the last column is what
ships.

| M8 case | 5 workers, on change (baseline) | 5 workers, every tick | 16 workers, on change | 16 workers, every tick |
|---|---|---|---|---|
| 5 streams held | 0.14 s | — | — | 0.11 s |
| 8 streams held | 0.07 s | — | — | 0.66 s |
| 12 streams held | 50.6 s | 24.4 s | 0.11 s | 0.12 s |
| 12 streams, clients killed at 3 s | 48.5 s | 8.3 / 8.5 / 4.2 s | 0.10 / 0.09 s | 0.08 / 0.08 s |
| 24 streams, clients killed at 3 s | — | — | 22.7 / 22.5 s | 0.09 / 4.3 s |

The 48.5 s baseline column reproduces RSRCH-001's 50.8 s / 49.4 s.

The 24-stream row is why the yield fix stays even though the worker bump
alone clears M8: at 16 workers, 24 abandoned tabs still park the pool for
22.5 s on the old generator and clear in under 5 s on the new one. The
worker count buys headroom; the yield is what returns a worker when a tab
closes.

### M4 — container CPU over 30 s (cgroup `usage_usec`)

| Config | 0 streams | 3 streams |
|---|---|---|
| RSRCH-001 baseline (5 workers, on change) | 1.4 % | 5.6 % |
| 5 workers, every tick | 0.26 % | 1.61 % |
| 16 workers, every tick | 0.37 % | 0.99 % |

Both post-change readings sit under the 5.6 % ceiling. The idle column is
also below the baseline's 1.4 %, so the host was quieter than it was on
2026-08-24; read the 3-stream figure against its own idle row rather than
across rows.

### Badge

`curl -s -N -m 10 http://localhost:8000/events` emits five `event: unread` /
`data: 0` frames, one per 2 s tick, where it emitted one before. `GET /`
still renders `data-live-badge="Messages" data-events-url=".../events"`; the
server-rendered count is covered by the layout-composer and messaging tests
in the suite.

`make check`: 1827 tests, 4946 assertions, 100.0 % line coverage, Pint and
PHPStan clean.

### Left out

- `UnreadCountStream::TICK_SECONDS` is unchanged at 2 s. It bounds how long
  a dead stream lingers; shortening it would multiply the `count` query rate
  for a gain M8 no longer needs.
- `live-badge.js` is unchanged — it was already idempotent.
- The container on port 8000 that these numbers were taken against still
  runs with 5 workers where the last column says 16; `PHP_CLI_SERVER_WORKERS`
  is read by `php -S` at start, so the compose change reaches it on the next
  `make up` (a `docker compose restart` does not re-read the environment).
  The 16-worker columns were measured on a second container started from the
  same compose service with `-e PHP_CLI_SERVER_WORKERS=16 -p 8001:8000`, and
  removed afterwards.

### Docs

Prose that described the removed behaviour, brought to the code:

- `docs/messaging.md` § "The live badge" — the sequence diagram's
  `alt the number moved` branch is gone, since every tick emits a frame; the
  worker count reads `16`; the stale "four concurrent streams … a fifth stream
  plus a page load both wait" measurement is replaced by the M8 numbers (twelve
  streams held → 0.11-0.12 s, twelve abandoned → 0.08-0.29 s) and by what now
  bounds concurrent readers; the `connection_aborted()` paragraph states the
  current mechanism (a frame per tick, a failed write is how PHP learns the
  client is gone, a worker back within one `TICK_SECONDS`) and keeps the cost
  that still holds — a live tab holds its worker for the whole lifetime. The
  cookieless-crawler sentence now says a worker is held until it disconnects.
- `docs/review.md` — the "The live badge" comparison against Node keeps its
  point (PHP polls on a tick and pays a worker per live stream) with the
  closed-tab clause replaced by one-tick reclamation and the worker count at
  16. Known gap 5 was "a closed messaging tab does not free its SSE worker at
  once"; it now names the gap that remains, a worker per open tab bounded by
  `PHP_CLI_SERVER_WORKERS`.
- `README.md` — the matching known-gaps bullet.

`docs/architecture.md`'s `UnreadCountStream` entry describes the clock (a
deadline from the controller, `now()` per tick) and is unaffected.
`work/3-done/FEAT-016` describes what that ticket built and is left as the
record it is.
