---
id: MAINT-003
type: maintenance
status: resolved
created: 2026-08-25
---

# MAINT-003: Expired rate-limit windows are pruned

## Problem
`checkRateLimit` (`app/actions/rate-limit/check-rate-limit.ts:33-48`) upserts one row per `(name, key, window_start)`, and nothing anywhere deletes from `rateLimitWindows` — grep finds no `deleteFrom('rateLimitWindows')` in the tree; the only sweep that exists is for stale orders. Every expired window survives forever: one row per client IP and per email per window on a public sign-in form. Lookups stay indexed (the unique `(name, key, window_start)` index), but the table and index grow monotonically, the upsert's probe deepens over time, and the database file never shrinks.

## Goal
The rate-limit store stays bounded by the windows that can still matter.

## Outcome
Rows whose window ended before the largest configured window's reach are removed on a regular cadence; every limit still trips and clears exactly as the FEAT-020 tests assert.

## Why it matters
It is the one unbounded-growth write path wired to unauthenticated traffic — a slow leak on the hot POST path whose cost compounds silently until the demo database is mostly dead rate-limit rows.

## Discovery notes
- The existing sweep CLI cadence (`sweep-stale-orders` / `make sweep`) is a natural home; cutoff = now − the largest configured window.
- An alternative is a cheap bounded `DELETE ... WHERE window_start < ?` piggybacked on the check itself; the CLI home keeps the hot path write-only.

## Related work
- FEAT-020 (configurable rate limits — built the windows table)

## Working

- 2026-08-25 — re-validated: `grep deleteFrom('rateLimitWindows')` finds nothing;
  `checkRateLimit` is still the only writer. Baseline 2012 tests, coverage
  99.42/95.85/99.38.
- Design: cutoff is a pure function in `app/core/rate-limit/` — a consult at
  `now >= asOf` targets `windowStart(now, W) > now − W >= asOf − maxW`, so rows
  with `window_start < asOf − maxW` can never be read again by any configured
  limit. `off` limits contribute no window; every limit `off` yields no cutoff
  and the prune deletes nothing (conservative: a redeploy could re-enable a
  limit mid-window).
- Home: the existing sweep CLI (`sweep-stale-orders.ts` / `make sweep`) — same
  `AS_OF`, idempotent, keeps the hot POST path write-only. `docs/alignment.md`
  §6.1 already names `sweep` as the scheduled-jobs verb, so no new make target.
- Logging: §2.3's event vocabulary is closed and has no rate-limit prune event,
  so the delete stays silent per the contract ("a write with no event above
  stays silent").
- Delivered: pure `expiredWindowCutoff(asOf, limits)` in
  `core/rate-limit/expired-window-cutoff.ts` (null when every limit is `off`);
  shell `pruneRateLimitWindows` in
  `actions/rate-limit/prune-rate-limit-windows.ts` — one
  `DELETE FROM rate_limit_windows WHERE window_start < toTimestamp(asOf − maxW)`,
  returns the deleted count; `sweep-stale-orders.ts` `main` runs it after the
  order sweep with `Object.values(config.rateLimits)` and the same `asOf`.
- Tests 2012 → 2022 (5 core, 4 action, 1 CLI); the action tests pin that a
  same-instant prune never forgives an active trip and that a row at exactly
  the cutoff survives. Coverage 99.43/95.86/99.38; `make check` green.
- Reviewer: accept — one comment fix applied (the CLI catch comment now covers
  the silent prune). Deferred observation: a prune-only failure exits 1 with no
  log line, which follows from the closed event vocabulary; ticket it if
  operational visibility ever needs it.
