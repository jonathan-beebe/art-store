---
id: FEAT-020
type: feature
status: open
created: 2026-08-23
---

# FEAT-020: Configurable rate limits on sign-in, posting, checkout, and payment

## Problem
No route is rate limited: a script can request unlimited magic links for one address, post unlimited messages, or hammer `POST /orders/:id/pay`. `docs/alignment.md` §3 fixes the limit names, env variables, value format, keys, and the 429 behaviour every prototype shares.

## Goal
Abuse of the write routes is bounded by limits an operator can tune from the environment.

## Outcome
Each of the seven §3 limits is enforced on the routes it names, read from its env variable at boot (defaults when unset, `off` disables, malformed refuses to boot), keyed as the table says; a trip answers 429 with `Retry-After`, the site's own HTML page or the re-rendered form, one `rate_limit.exceed` log line, and no side effect; counters survive a restart; tests cover each limit's trip and reset and the config parser's edge cases.

## Why it matters
The three prototypes are judged as production candidates; an unlimited sign-in form is a mail bomb and an unlimited pay route is a card-testing tool.

## Discovery notes
Platform-first: a `rate_limit_windows (name, key, window_start, count)` table with an upsert in the same SQLite file, a pure `parseRateLimit("5/15m")` → `{count, windowSeconds}`, and a Fastify `preHandler` factory per limit. Env parsing lives with the existing boot-time config validation (BUG-006). Client ip from the socket unless `TRUSTED_PROXIES` is set.

## Related work
- docs/alignment.md §3
- BUG-006 (boot-time config)
