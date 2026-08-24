---
id: FEAT-020
type: feature
status: resolved
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

## Working

Baseline `ba45e5e`: 1747 tests, coverage 99.52% lines / 97.30% branches /
99.53% functions. After: 1805 tests, coverage 99.53% lines / 97.28% branches /
99.55% functions.

### What was built

- **Parser** — `parseRateLimit(raw, defaultValue)` in
  `app/core/rate-limit/rate-limit-value.ts`: `(raw: string | undefined,
  defaultValue: string) => { ok: true; value: 'off' | { count: number;
  windowSeconds: number } } | { ok: false; error: string }`. Pure, no
  throwing. `raw ?? defaultValue` is where the "unset falls back to the
  default" rule lives, so the same function covers both a real env value and
  the fallback with one code path. Tests:
  `app/core/rate-limit/rate-limit-value.test.ts`.
- **Fixed-window decision** — `app/core/rate-limit/rate-limit-window.ts`:
  `windowStart(now, windowSeconds)` floors `now` to the window boundary;
  `decideRateLimit(count, limitCount, start, windowSeconds, now)` decides
  `{tripped: false} | {tripped: true, retryAfterSeconds: number}` from the
  count a counter already came back with. `retryAfterSeconds` is
  `ceil((windowEnd - now) / 1000)`, floored at 1.
- **Message** — `tooManyRequestsMessage(retryAfterSeconds)` in
  `app/core/rate-limit/too-many-requests.ts` renders exactly `"Too many
  requests — try again in N minutes."`, `N = max(1, ceil(retryAfterSeconds /
  60))`. **Always plural** ("1 minutes"), deliberately, so PHP and Rails can
  match byte for byte without a pluralization rule to keep in sync across
  three languages.
- **Redaction** — `redactedRateLimitKey(key)` in
  `app/core/rate-limit/redacted-key.ts`: `sha256(key).hex().slice(0, 16)`. The
  `rate_limit.exceed` line's `data.key` is this digest of `email:<lowercased
  address>`, `ip:<client ip>`, or the actor/order/customer id the limit is
  keyed by — never the raw value. Chosen over a shorter hash or a truncated
  raw value because it is the same primitive `digestMagicLinkToken` already
  uses for the same reason (`app/core/auth/magic-link-token.ts`), so the
  choice needed no new justification.
- **Table** — `rate_limit_windows (id text pk, name text check(...), key
  text, window_start text, count integer default 0)`, unique index on
  `(name, key, window_start)`. Migration
  `app/db/migrations/20260824090000-create-rate-limit-windows.ts`. Row type
  in `app/db/commerce-schema.ts`; sample in `app/db/schema-fidelity.test.ts`.
  `rlw` added to `ID_PREFIXES`.
- **Counter** — `checkRateLimit(context, limit, {name, key})` in
  `app/actions/rate-limit/check-rate-limit.ts`: one `insertInto(...)
  .onConflict(...doUpdateSet({count: sql\`rate_limit_windows.count + 1\`}))
  .returning('count')` — an atomic upsert-and-read, no transaction needed
  because there is no separate read before the write. Tested for trip,
  reset, `off`, independent keys/names, and restart survival (two
  `AppDatabase` instances over one file, see
  `app/actions/rate-limit/check-rate-limit.test.ts`).
- **Config** — seven `RATE_LIMIT_*` env vars plus `TRUSTED_PROXIES`, parsed
  in `config.ts`'s `toAppConfig`; a malformed value throws
  `"${ENV_VAR}: ${parseRateLimit error}"`, which `refuseUnsafeProduction`'s
  sibling machinery turns into a boot refusal for every environment, not
  just production (a bad limit is wrong everywhere, not just in prod).
  `config.rateLimits: Record<RateLimitName, RateLimit>`.
- **preHandler factories** — `app/plugins/rate-limit.ts`:
  - `rateLimitGuard<Params>({name, key: (request) => string}) =>
    preHandler` for the six single-counter limits. `Params` is inferred the
    same way `refuseBlockedCustomer`'s `Params` is (an explicit generic
    argument or a named function with its own parameter type), so a `key`
    that reads `request.params.id` type-checks against the route's own
    `idParams` schema.
  - `magicLinkRequestGuard<Body>(email: (request) => string) => preHandler`
    for the dual-counter `magic_link_request`: checks the email-keyed
    counter first, then the ip-keyed counter only if the email counter did
    not trip — both counters still increment on every call that reaches
    them, only the decision short-circuits.
  - `magicLinkRequestDecision(request, email)` and `answerIfRateLimited(...)`
    are exported separately so a route that sends a magic link
    conditionally (guest checkout) can call the same check and the same 429
    answer inline, without a `preHandler` gating the whole route.
  - `clientIp(request)` reads `request.ip` — Fastify's own `trustProxy`
    computing it, per the ticket's steer away from hand-parsing
    `X-Forwarded-For`.
- **`TRUSTED_PROXIES`** — a new, separate config field
  (`config.trustedProxies: string[] | null`), left alongside the existing
  `TRUST_PROXY` boolean rather than replacing it. `app.ts` now passes
  `trustProxy: config.trustedProxies ?? config.trustProxy` to Fastify's
  constructor: set, the named list governs `request.ip` (and
  protocol/host, more precisely than the boolean — only those hops are
  trusted); unset, the boolean's existing behavior is unchanged bit for bit,
  so every `TRUST_PROXY`-pinned test kept passing untouched.

### A bug the tests caught: FastifyReply is a thenable

`answerIfRateLimited` originally returned the `FastifyReply` it wrote to.
`FastifyReply` implements `.then` (Fastify's own support for the "return
`reply`" idiom in a route handler), so returning one from a plain `async`
helper function has JavaScript treat it as a thenable and assimilate it: the
caller's `await` resolves to whatever `reply.then` resolves with
(`undefined`), not the reply itself. Every `preHandler` use was silently
unaffected (Fastify only checks `reply.sent`, never the resolved value), but
checkout.ts's own inline `if (limited !== undefined) return limited` check
never saw a trip — the guarded action ran anyway after answering 429. Fixed
by having `answerIfRateLimited` return a plain `boolean`; a `preHandler`
turns that into `tripped ? reply : undefined` itself. Caught by the
`checkout's implicit magic link ...` integration test, which is exactly why
that test (not just the core unit tests) mattered.

### The seven limits, wired

| Limit | Env | Guards | Key |
| --- | --- | --- | --- |
| `magic_link_request` | `RATE_LIMIT_MAGIC_LINK_REQUEST` | `POST /login` (all 3 sites, `sites/auth/sign-in-routes.ts`); the implicit link `POST /checkout` sends a guest (`sites/shop/routes/checkout.ts`, inline, not a `preHandler`) | lowercased email + client ip, separately |
| `magic_link_consume` | `RATE_LIMIT_MAGIC_LINK_CONSUME` | `GET /auth/magic/:token` (`sites/auth/index.ts`) | client ip |
| `message_post` | `RATE_LIMIT_MESSAGE_POST` | shop `POST /messages/:id`, shop `POST /art/:slug/questions`, seller `POST /messages/:id`, admin `POST /messages/:id` | actor id |
| `conversation_open` | `RATE_LIMIT_CONVERSATION_OPEN` | shop `POST /art/:slug/questions` (also `message_post`, both apply — it opens and posts in one transaction), shop `GET /support`, shop `POST /orders/:id/fulfillments/:fulfillmentId/messages`, seller `GET /support` | actor id |
| `checkout` | `RATE_LIMIT_CHECKOUT` | shop `POST /checkout` | customer id |
| `payment_attempt` | `RATE_LIMIT_PAYMENT_ATTEMPT` | shop `POST /orders/:id/pay` | order id |
| `listing_write` | `RATE_LIMIT_LISTING_WRITE` | seller `POST /listings`, seller `POST /listings/:id` | seller id |

### Trip behaviour

429, `Retry-After: <retryAfterSeconds>`, one `rate_limit.exceed` line at
`warn` (`data.limit`, `data.key` redacted, `data.retry_after_seconds`), and
the site's own `error` template (the same one a 400/500 renders) — or plain
text for the one route with no site layout (`GET /auth/magic/:token` sits at
the root). No side effect: every guard runs as a `preHandler`, before the
route's handler does anything, except `magic_link_request` on checkout,
which is checked inline immediately before the `sendMagicLink` call it
guards and after the order (a different limit's concern) is already placed.

### Deliberate cuts (recorded in `docs/review.md`'s Known gaps as #11 and #12)

1. **The form re-render polish.** §3 says a form re-renders the sentence as
   a field-less error; every trip instead answers the generic 429 page.
   Explicitly the lowest-priority item in the ticket's own cut order.
   `answerIfRateLimited` would need an optional `onTrip` callback per route
   to do this properly.
2. **Admin's `POST /sellers/:id/messages` and `POST
   /customers/:id/messages`** carry no `conversation_open` guard. They open
   a conversation with no message body — the same shape as the
   fulfillment-thread open, which is guarded — but `docs/alignment.md` §3's
   guard list names only "listing question, support, fulfillment thread
   opens." Left unguarded rather than stretching the contract's literal
   list; an admin is authenticated regardless.

### Test config default

`test/build-test-app.ts`'s `TEST_CONFIG.rateLimits` sets every limit `off`
by default (previously nonexistent). Turning on the §3 defaults there would
have silently broken hundreds of existing tests that legitimately hit a
guarded route more than the default count within one test. A test about one
limit overrides just that limit via
`{...TEST_CONFIG, rateLimits: {...TEST_CONFIG.rateLimits, <name>: {count,
windowSeconds}}}` (see `app/plugins/rate-limit.test.ts`).

### No deviations from the contract's shapes

Names, env variables, defaults, key rules, and the 429 behaviour match
`docs/alignment.md` §3 exactly except the form re-render (cut, above). The
`rate_limit_windows` table matches the `(name, key, window_start, count)`
shape §3 names.

### Fix-up

Two items from the FEAT-020 review, against contract `docs/alignment.md`
§2.3 and §3.

1. **The missing `magic_link.request` `refused` line.** The `magic_link_request`
   guard runs as a `preHandler`, before `sendMagicLink` ever opens its own
   `will`/`did` story, so a tripped limit wrote `rate_limit.exceed` and
   nothing else. `answerIfRateLimited` (`app/plugins/rate-limit.ts`) now also
   writes a `magic_link.request` `refused` line at `info` when `name` is
   `magic_link_request`, reusing the same `redactedRateLimitKey` digest —
   `{"event":"magic_link.request","phase":"refused","data":{"reason":"rate_limited","key":"<digest>"},"msg":"refused to send a sign-in link over the rate limit"}`.
   Covers both call sites keyed by that limit: sign-in's `POST /login`
   (`magicLinkRequestGuard`) and checkout's implicit link
   (`sites/shop/routes/checkout.ts`'s inline call). The not-admitted half
   stays out of scope for `IMPRV-011`. `app/plugins/rate-limit.test.ts` adds
   a test asserting both lines and that neither carries the address or the ip.

2. **`TRUST_PROXY` / `TRUSTED_PROXIES`.** Both fed the same Fastify
   `trustProxy` constructor option — one as a plain boolean (trust every
   forwarded header, no address check), the other as an address/CIDR list —
   so they were two encodings of one setting, not two concerns. Collapsed to
   `TRUSTED_PROXIES` alone, the one `docs/alignment.md` §3 names:
   `config.ts` drops the `TRUST_PROXY` variable and the `trustProxy` config
   field; `app.ts` reads `trustProxy: config.trustedProxies ?? false`. Unset,
   every environment trusts nothing forwarded and reads the raw socket, same
   as development always did. `config.test.ts`, `test/build-test-app.ts`,
   and the README's environment-variable table updated to match.

`make check`: 1805 tests, coverage 99.53% lines / 97.28% branches / 99.55%
functions — unchanged from baseline `6a7ba03`.
