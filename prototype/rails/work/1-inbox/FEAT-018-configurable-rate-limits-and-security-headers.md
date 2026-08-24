---
id: FEAT-018
type: feature
status: open
created: 2026-08-23
---

# FEAT-018: Configurable rate limits and security headers

## Problem
No route is rate limited: unlimited magic links per address, unlimited message posts, unlimited card attempts on `POST /orders/:id/pay`. The CSP initializer is commented out and no response carries the security headers Node sends. `docs/alignment.md` §3 fixes the limit names, env variables, value format, keys, and 429 behaviour every prototype shares.

## Goal
Abuse of the write routes is bounded by limits an operator can tune from the environment, and responses carry the same security headers as the other prototypes.

## Outcome
Each of the seven §3 limits is enforced on the routes it names, read from its env variable at boot (defaults when unset, `off` disables, malformed refuses to boot), keyed as the table says; a trip answers 429 with `Retry-After`, the site's own HTML page or the re-rendered form, one `rate_limit.exceed` log line, and no side effect; counters survive a restart; CSP (`default-src 'self'`, `form-action 'self'`, `frame-ancestors 'none'`, with Turbo/importmap allowed), `X-Content-Type-Options`, `Referrer-Policy`, and HSTS in production are on every response; tests cover each limit's trip and reset, the config parser's edge cases, and the headers.

## Why it matters
An unlimited sign-in form is a mail bomb and an unlimited pay route is a card-testing tool; the three prototypes are judged as production candidates.

## Discovery notes
Vanilla Rails: the `rate_limit to:, within:, by:, with:` controller macro (Rails 7.2+) backed by `Rails.cache` on Solid Cache (or the SQLite-backed store), the seven limits parsed from env in an initializer into a frozen hash the controllers read by name; the content-security-policy initializer un-commented.

## Related work
- docs/alignment.md §3
- prototype/node BUG-006 (security headers)
