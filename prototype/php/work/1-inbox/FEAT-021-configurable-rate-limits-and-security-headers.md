---
id: FEAT-021
type: feature
status: open
created: 2026-08-23
---

# FEAT-021: Configurable rate limits and security headers

## Problem
No route is rate limited: unlimited magic links per address, unlimited message posts, unlimited card attempts on `POST /orders/{order}/pay`. No response carries a Content-Security-Policy or the other security headers Node sends. `docs/alignment.md` §3 fixes the limit names, env variables, value format, keys, and 429 behaviour every prototype shares.

## Goal
Abuse of the write routes is bounded by limits an operator can tune from the environment, and responses carry the same security headers as the other prototypes.

## Outcome
Each of the seven §3 limits is enforced on the routes it names, read from its env variable at boot (defaults when unset, `off` disables, malformed refuses to boot), keyed as the table says; a trip answers 429 with `Retry-After`, the site's own HTML page or the re-rendered form, one `rate_limit.exceed` log line, and no side effect; counters survive a restart; CSP (`default-src 'self'`, `form-action 'self'`, `frame-ancestors 'none'`), `X-Content-Type-Options`, `Referrer-Policy`, and HSTS in production are on every response; tests cover each limit's trip and reset, the config parser's edge cases, and the headers.

## Why it matters
An unlimited sign-in form is a mail bomb and an unlimited pay route is a card-testing tool; the three prototypes are judged as production candidates.

## Discovery notes
Idiomatic Laravel: `RateLimiter::for()` per limit in the app service provider with `throttle:<name>` middleware, the limits parsed from `config/rate_limits.php` (env-backed), the cache store set to `database` so counters persist; a `config` value-object parser for `"5/15m"`. Headers from one middleware in the web group.

## Related work
- docs/alignment.md §3
- prototype/node BUG-006 (security headers)
