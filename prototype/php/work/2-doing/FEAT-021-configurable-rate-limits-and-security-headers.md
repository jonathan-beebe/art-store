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

## Working

Landed on `align/php`, commit to follow.

**What was built.** `App\Domain\RateLimiting\RateLimitValue::parse()` is the
pure parser (`"<count>/<window>"` or `"off"`), covered by a Pest dataset for
every window unit, `off`, malformed count/window/slash, zero/negative count,
whitespace, and empty string. `App\Domain\RateLimiting\RateLimitName` names
the seven limits and their env variable/default, so `config/rate_limits.php`
builds the config by mapping `RateLimitValue::parse()` over the enum's cases
rather than repeating the table by hand. `App\Support\RateLimiting\RateLimitGate`
wraps `Illuminate\Cache\RateLimiter` over the `database` cache store
(`checkEach()` checks every key before hitting any of them, so
`magic_link_request`'s email and ip budgets trip independently and a refused
request marks neither), writes the one `rate_limit.exceed` line at `warn`
(`StoryEvent::RateLimitExceed`, new `refusalLevel()` branch), and throws
`App\Domain\RateLimiting\RateLimitExceeded`. Each guarded controller action
checks ahead of the write it guards (before `PlaceOrder`, `FinalizeOrder`,
`OpenConversation`, `PostMessage`, `SendMagicLink`, `CreateListing`,
`UpdateListing` run), so a trip performs no side effect. `App\Http\Middleware\SecurityHeaders`
sets CSP/`X-Content-Type-Options`/`Referrer-Policy` on every response and
HSTS in production, registered as a *global* middleware (not scoped to the
`web` group — see Deviations). `bootstrap/app.php` wires `TrustProxies` from
`TRUSTED_PROXIES` when set.

**Email redaction.** `magic_link_request` is keyed by email per §3. The
caller — the three login `send()` methods and `CheckoutController::place()` —
hashes the normalized address (`'email:'.hash('sha256', EmailNormalizer::normalize($email))`)
*before* it ever reaches `RateLimitGate`, so the cache key and the value
`rate_limit.exceed` logs are the same hash; the gate never holds a raw
address to redact. Covered by `CustomerLoginControllerTest`'s
`'logs the trip as rate_limit.exceed at warn with the email hashed rather
than in the clear'`.

**Malformed value refuses to boot.** `config/rate_limits.php` calls
`RateLimitValue::parse()` for all seven while the config file loads, and
config files load on every boot (every artisan command, every request)
before a route is ever dispatched. `App\Support\RateLimiting\RateLimitsConfigTest`
proves it by `require`ing the file directly with `RATE_LIMIT_CHECKOUT` set
to a bad value via `putenv()` and asserting the `require` itself throws,
naming the variable.

**Deviations from the discovery notes / literal ticket text, with reasoning.**
- *Security headers: global middleware, not the `web` group.* The ticket
  says "One middleware in the web group," but a request that matches no
  route never reaches a group — `LogRequestStory` is already global for the
  same reason, and Node's own security-headers test explicitly covers a
  404-for-nothing (`prototype/node/src/app/plugins/security-headers.test.ts`).
  Registered as global instead so parity holds; `SecurityHeadersTest` covers
  a shop page, a seller page, an admin page, and an unmatched route.
- *`img-src 'self' data:` beyond the ticket's three named CSP directives.*
  `App\Support\PlaceholderImage` renders a listing with no photograph as an
  inline `data:image/svg+xml` `<img src>`; `default-src 'self'` alone blocks
  it. Node's own CSP already carries the same allowance for the same
  reason. Confirmed nothing else needed a nonce or a hash — no inline
  `<script>` or `<style>` exists anywhere in `resources/views`; Vite emits
  `<link>`/`<script src>` tags, and the live-badge `EventSource` is
  same-origin, covered by `default-src` with no `connect-src` needed.
- *No literal `RateLimiter::for()` / `throttle:<name>` middleware.* The
  discovery notes suggested it, but three requirements didn't fit it
  cleanly: `magic_link_request`'s two independent keys, the response
  splitting into "re-render this exact form" vs. "the site's generic page"
  per route, and the checkout-embedded implicit link check that isn't a
  route at all. `RateLimitGate` (a thin wrapper over the same underlying
  `Illuminate\Cache\RateLimiter` the built-in middleware itself uses) gives
  the same idiom — a named limit, a key, a budget — without fighting the
  built-in middleware's one-size response and per-route parameterization.
- *`checkout`'s embedded `magic_link_request` check runs on
  `! $purchaser->isEmailVerified()`, not after `SendMagicLink` is called.*
  `OrderPlacement`'s status assignment already keys off the same predicate
  (`OrderPayment::isPayableBy()`), so checking it before `PlaceOrder` runs
  soundly predicts whether the magic-link branch below will fire — letting
  both the `checkout` and the embedded `magic_link_request` checks complete
  with no order ever created on a trip.
- *`ListingQuestionController` counts against `conversation_open`, not
  `message_post`,* even though it also posts a message in the same action —
  matching the contract's literal route grouping ("listing question... "
  under `conversation_open`), the way `Admin\SellerMessageController` and
  `Admin\CustomerMessageController` count against `message_post` instead
  (they're not named under `conversation_open`'s three groups).
- *`magic_link_consume`'s 429 always renders in the shop layout.* The route
  (`/auth/magic/{token}`) is shared by all three sites and the trip happens
  before the link row — and so the actor type — is ever read, so there is no
  signal to pick seller or admin from. Shop is the default/public-facing
  site; a documented, defensible choice rather than a real per-site answer.

**Left out.** No `X-Frame-Options` (Node carries one; `frame-ancestors
'none'` in the CSP already supersedes it in the browsers this prototype
targets, and the ticket's header list doesn't name it). No `style-src`/
`script-src` CSP directives beyond `default-src` — nothing in this
prototype needs them separately.

**What Node and Rails must match:** the seven limit names/env vars/defaults/
keys/guards from §3 (unchanged here — just implemented); the 429 shape
(status, `Retry-After`, one `rate_limit.exceed` line, no side effect); the
CSP's three named directives plus `img-src 'self' data:` if either lane also
renders an inline data-uri image; `Referrer-Policy: strict-origin-when-cross-origin`
(already Node's value, kept consistent here); HSTS `max-age=63072000;
includeSubDomains` in production only.

**Open questions.** None blocking. Whether Node/Rails also need the
`img-src data:` CSP allowance depends on whether their own placeholder-image
mechanism is inline (Node's already has it; Rails' is untouched by this
ticket).
