---
id: FEAT-033
type: feature
status: resolved
created: 2026-08-29
---

# FEAT-033: Log store and admin log viewer

## Problem
`docs/logging.md` and `docs/alignment.md` §2.5 fix the log store contract — every stdout line mirrored into a SQLite file of its own, browsable from the admin — and the Node prototype already implements it (`prototype/node/docs/log-store.md`). The PHP prototype logs the §2 stories to stdout and nowhere else: a founder asking "what happened to this order?" has only terminal scrollback.

## Goal
Every JSON line this prototype logs — server and CLI alike — lands in a queryable `log_lines` store, and `/admin/logs` reads it back: a filterable time series with a per-request story view, per the `docs/logging.md` contract.

## Outcome
- [x] `App\Logging\LogStore` — own PDO handle over `LOG_DATABASE_FILE` (default `storage/logs.sqlite3`, `off` disables), ensure-on-open schema versioned by `user_version`, WAL + 250ms busy timeout + incremental auto-vacuum, buffer/flush ingest with `register_shutdown_function` exit flush; any store failure degrades to stdout-only logging.
- [x] `App\Logging\LogStoreTap` on the `stdout` channel appends `LogStoreHandler` after the stream handler, so stdout writes first and both share one `StoryFormatter` instance.
- [x] `LOG_RETENTION_DAYS` (default 14, `off` disables, malformed refuses boot) prunes inside `orders:sweep`, which gains `--as-of`; sweep and prune failures isolate in both directions.
- [x] `GET /admin/logs` — 50-row pages newest first, level stat tiles, the full filter set (domain / level / phase / event / request / txn / session / actor / msg / from / to / key+value / group / health), unrecognised values answer 400, severity tint, prefixed ids linkified to their admin pages.
- [x] `GET /admin/logs/requests/{requestId}` — the story view: header stats, lines in order, 1,000-line cap with notice, 200 empty state for an unknown well-formed id, 404 for a malformed one.
- [x] `make check` green; coverage 100%; 2,960 tests; live-verified against the running dev server (signed-in admin, filters, 400s, story view, grouping).
- [x] `docs/log-store.md` written; `docs/README.md` and `docs/admin.md` index the new pages; journal updated.

## Why it matters
The admin can now answer "what is the app doing?" from the browser: the whole request story behind any order, refund, or failure, filterable by actor, transaction, or any attribute of the line — with stdout untouched as the canonical §2 surface.

## Working

**Two dev-environment defects surfaced during live verification, both invisible to the CLI-only test suite.** The local `.env` had drifted to `LOG_CHANNEL=stack`, sending every story line to `storage/logs/laravel.log`; corrected to `stdout` per `.env.example` (the serve process bakes env at boot, so the container needed a restart). And `LogStore` referenced the `STDOUT`/`STDERR` constants, which exist only in the CLI SAPI — under `artisan serve`'s `cli-server` workers the stdout channel failed to build and Laravel fell back to the emergency logger on every request. The default writers now open `php://stdout`/`php://stderr` per write. Both recorded in `docs/log-store.md`.

**PDO adaptation of the numeric any-attribute match.** PDO's array-`execute()` binds every parameter as text, and SQLite does not coerce TEXT against INTEGER, so the numeric side binds as `CAST(? AS REAL)` where Node binds a typed number. Caught by a failing test.

**The reverse failure-isolation branch needed its own test** — an order-sweep crash still runs the log prune and fails the command — closing the last coverage gap (`SweepOrdersTest`).
