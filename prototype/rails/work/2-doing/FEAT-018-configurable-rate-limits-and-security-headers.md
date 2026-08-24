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

## Working

All seven §3 limits enforced on the routes named, keyed as the table says, read from env at boot with default/off/malformed handling, 429 + `Retry-After` + `rate_limit.exceed` log line + no side effect on a trip, counters surviving a restart, and CSP + the existing-by-default `X-Content-Type-Options`/`Referrer-Policy`/HSTS on every response. Nothing was cut from the non-negotiable core.

**Config parser** (`config/initializers/rate_limits.rb`): `RateLimits::CONFIG`, a frozen hash of `RateLimits::Limit`s (`name`, `count`, `window_seconds`, `enabled`), built by `RateLimits.parse(name, env_var, raw)` — a pure, directly-testable function the eager `CONFIG` constant calls at load time, so a malformed value raises during initialization the same way it would from any other `config/initializers/*.rb` file. Value regex `\A([1-9]\d*)\/([1-9]\d*)(s|m|h)\z` — `0/15m` and `-1/15m` fall out of the same "not this shape" branch as `abc`, needing no separate zero/negative check. `config/` is excluded from SimpleCov's coverage (`test/coverage_boot.rb`), so this file's own lines don't count toward the 100% gate, but `test/lib/rate_limits_test.rb` covers every edge case in the ticket's list directly against `.parse`.

**Cache-store decision**: a dedicated `SolidCache::Store` instance (`RateLimits::STORE`), not `Rails.cache`. `config.cache_store` is `:memory_store` in development and `:null_store` in test (neither survives a restart, and `:null_store` would never trip at all, which would silently fail every trip/reset test) — Rails' `rate_limit` macro takes a `store:` override for exactly this reason ("if you don't want to store rate limits in the same datastore as your general caches"). `solid_cache` is added to the `Gemfile` and wired the same single-database way `solid_cable` already is for Action Cable: no `config/cache.yml`, no `connects_to`, so the gem falls back to the default Active Record connection; `db/migrate/20260824000102_create_solid_cache_entries.rb` adds `solid_cache_entries` to this app's own `db/schema.rb`. `config.cache_store` itself is untouched — general app caching in dev/test is out of scope here.

**Fixed window over the macro**: Rails' `rate_limit to:, within:, by:, with:` macro calls `store.increment(cache_key, 1, expires_in: within)`, which is a sliding count whose TTL a store may push back on every hit, not a window tied to a `window_start`. `RateLimiting.windowed_key` folds `(now / window_seconds) * window_seconds` into the string `by:` returns, so the key itself changes every window and the counter it addresses starts back at zero without depending on how any particular store's `increment` treats `expires_in` on an existing entry.

**No exceptions across frames**: six of the seven limits gate one action outright and are declared with `rate_limit_guard name, by:, only:`, a thin wrapper over the macro whose `with:` renders the 429 directly (no raise) — this is what a `before_action` halting on a render already does. `magic_link_request` keys on the address and, separately, the ip, and guards one shared method (`MagicLinkSender#send_magic_link`) called from four different actions, so it can't be a single `rate_limit_guard`. First attempt raised a custom exception and caught it with `rescue_from` — this landed on `RequestStory`'s own `rescue StandardError` first (closer to the raise point than `rescue_from`, which only catches at the `process_action` level), which logged the request's `did` line with a guessed status (`ActionDispatch::ExceptionWrapper.status_code_for_exception` doesn't know the exception, defaults to 500) instead of the 429 actually answered. Fixed by not raising at all: `rate_limit_trip!` renders the 429 itself and returns the trip-or-nil; `send_magic_link` returns `nil` on a trip, and each of its four callers (`Auth::SellerSessionsController#create`, `Auth::CustomerSessionsController#create`, `Auth::AdminSessionsController#create`, `Shop::CheckoutsController#send_verification_link`) returns early instead of touching a `nil` link.

**Email redaction**: `docs/alignment.md` §2.1 keeps email addresses out of `data`, but §2.3 says `rate_limit.exceed`'s `data` carries `key`, and `magic_link_request`'s key is literally an address for one of its two checks. Not spelled out in the alignment doc as an explicit interaction. Resolved by logging `key.include?("@") ? ActiveSupport::ParameterFilter::FILTERED : key` — the same `"[FILTERED]"` sentinel `RequestStory` already uses for masked path segments, so a `magic_link_request` trip's line carries `"key":"[FILTERED]"` and every other limit's line carries the raw id or ip.

**CSP nonce generator deviates from Rails' own commented template**: the template suggests `->(request) { request.session.id.to_s }`, but most of this app's requests — an anonymous storefront visit in particular — never write into `session[]` (identity rides a signed cookie instead), which left `session.id` blank and importmap's inline script tags carrying an empty `nonce-`. Switched to `SecureRandom.base64(16)` per request; nothing on any page needs the nonce to repeat across requests (no fragment caching keys on it).

**`magic_link_consume`'s 429 has no site to answer in**: `Auth::MagicLinksController#show` is one shared verification endpoint for all three sign-in flows, and the ip-keyed limit trips before the token is even looked up, so there is no actor and no way to know which site the visitor was headed to. Deviation: it renders in the storefront's (`shop`) layout, the nearest thing this endpoint has to a home, rather than a fourth, site-less page. Documented here since `docs/alignment.md` §3 just says "the site's own HTML page."

**`conversation_open` scope**: wired on the five routes the ticket names by category — `Shop::ListingQuestionsController#create`, `Shop::SupportsController#create`, `Shop::FulfillmentConversationsController#create`, `Seller::SupportsController#create`, `Seller::OrderConversationsController#create` — not on `Admin::CustomerConversationsController#create` / `Admin::SellerConversationsController#create`, which open threads from the admin side and match none of "listing question, support, fulfillment thread opens".

**Form vs. plain page**: a limit gets the form-re-render treatment where the route it guards already had a template-based refusal convention to extend (`Auth::*SessionsController#create` → `:new`, `Shop::CheckoutsController#create`/`send_verification_link` → `:show`, `Shop::OrderPaymentsController#create` → `:show`, `Seller::ListingsController#create`/`#update` → `:new`/`:edit`, `MessagingSite#create` → `thread_template`) via a `render_too_many_requests` override; everything else (`magic_link_consume`, `conversation_open`'s five routes, which already answer a refusal with a redirect + flash rather than a re-rendered form) falls through to the shared `application/rate_limit_exceeded.html.erb` plain page.

**Security headers**: `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin` were already on every response — Rails' own `config.action_dispatch.default_headers` default, confirmed by inspection rather than assumed. `config.force_ssl = true` already in `config/environments/production.rb` already adds `Strict-Transport-Security`. Only the CSP initializer needed writing: `default-src 'self'`, `img-src 'self' data:` (the generated SVG placeholder), `style-src 'self'`, `script-src 'self'` with a nonce (Turbo needs none — no inline scripts of its own; importmap's own `<script type="importmap">` and module tags carry the nonce automatically via `request.content_security_policy_nonce`, which `importmap-rails` reads without any wiring on this app's side), `form-action 'self'`, `frame-ancestors 'none'` — the same set `prototype/node`'s `security-headers.ts` sends.

**Left out**: no dedicated test forces `Seller::ListingsController#render_too_many_requests`'s `:edit` branch specifically (the `:new` branch is exercised, and SimpleCov here is line, not branch, coverage, so the ternary's one line is fully covered either way). No automated test exercises `Strict-Transport-Security` in production (the test suite runs in `test` env; relying on `force_ssl` being on in `production.rb` is a read, not a run). Neither is a functional gap — `docs/review.md`'s known-gaps list is unchanged.
