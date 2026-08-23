---
id: BUG-006
type: bug
status: resolved
created: 2026-08-23
---

# BUG-006: Production-unsafe defaults: debug magic-link alert, cookie secret, insecure cookies, Host-derived links

## Problem
`app/views/partials/debug-alert.ejs:1-6` is included unconditionally by all
three layouts (`sites/shop/views/layout.ejs:10`,
`sites/seller/views/layout.ejs:10`, `sites/admin/views/layout.ejs:10`), fed by
`app/delivery/flash-magic-link-delivery.ts:10`, and selected by
`app/config.ts:28` — `MAGIC_LINK_DELIVERY: z.enum(MAGIC_LINK_DELIVERIES).default('flash')`.
The partial has no environment guard: it prints a live, clickable sign-in link
into the page whenever `flash.debugMagicLink` is set, and the config
**defaults** to the delivery that sets it. The only other option, `mail`,
throws `NotImplementedError` (`mail-magic-link-delivery.ts:8`), so there is no
configuration under which the app both delivers links and does not print
them.

`app/config.ts:24-26` — `COOKIE_SECRET: z.string().min(16).default('art-store-prototype-cookie-secret')`.
Identity is a signed cookie holding a bare integer
(`plugins/identity.ts:67`), and `findAdmin` trusts it after unsigning
(`plugins/identity.ts:124-135`). Anyone holding the checked-in default can
mint `admin_id=1` and reach every admin route. The comment acknowledges the
tradeoff ("a deployment overrides it"), but nothing enforces the override.

No `secure: true` anywhere in the tree (`plugins/flash.ts:36-41`,
`plugins/identity.ts:67-73`), and Fastify is constructed with no
`trustProxy` (`app.ts:51`). Deployed behind a TLS terminator, the three
year-long identity cookies would be transmitted over any plaintext request to
the same host.

`app/sites/auth/request-origin.ts:9,13` builds magic-link URLs as
`` `${request.protocol}://${request.host}` ``, used at
`sites/auth/sign-in-routes.ts:86` and `sites/shop/routes/checkout.ts:125`.
`request.protocol` is `http` unless the socket itself is TLS, so behind a
terminating proxy every emailed link would be `http://`. `request.host` is
the client's `Host` header; with the `mail` delivery implemented, a request
carrying `Host: attacker.example` mints a link pointing there while the row
is created for the victim's address. The `flash` delivery in use today hands
the URL back to the same requester, so nothing is exploitable yet — the seam
is.

`app/app.ts:55` decorates `config` on the instance
(`grep -rn "\.config" app/` outside tests returns only that line) and nothing
reads `server.config` — the things that should be config (uploads dir, public
root, cookie `secure`) are hardcoded constants instead.

No security headers on any response — `app.ts:45-77` has no
`@fastify/helmet`, no `onSend` header hook, no `Content-Security-Policy`,
`X-Content-Type-Options`, `Referrer-Policy`, or `X-Frame-Options` anywhere in
the tree.

## Goal
A production boot refuses unsafe defaults instead of silently running with them.

## Outcome
- With `NODE_ENV=production` the app refuses to boot without `COOKIE_SECRET` set.
- The debug magic-link alert never prints into a page in production.
- Cookies are `secure` in production.
- The app trusts the configured proxy for protocol/host.
- Magic-link URLs are built from `PUBLIC_URL`, not the `Host` header.
- Every response carries `nosniff` / frame / referrer / CSP headers.
- Development keeps today's zero-config flow.

## Why it matters
"flash/debug partials must not leak in production" — the debug alert has no
environment guard and the config default routes every unconfigured
deployment through it. "config from env" and "env validated at boot,
crash-loud on bad env" — `COOKIE_SECRET`'s working default means a production
boot with no secret succeeds instead of failing loud, and admin cookies
become forgeable. Deployment doctrine ("container that runs anywhere") covers
`secure` cookies and `trustProxy` — neither is wired to any config signal
today. "A request header is unparsed input" — the `Host`-derived link is
exactly that, latent until the `mail` delivery is implemented (see FEAT-015).
"Cross-cutting concerns as plugins at the boundary" covers the missing
security headers; escaped templates and no client JS keep the baseline XSS
surface small, but `nosniff` and a CSP are exactly what would have contained
the upload issue in BUG-002.

## Discovery notes
Make the debug alert's inclusion conditional on something the config states
explicitly (e.g. a `showsDebugMagicLinks` flag derived at boot, false unless
`MAGIC_LINK_DELIVERY === 'flash'` **and** an explicit dev signal), and flip
the config default to `mail` so a deployment that forgot to configure a
mailer fails loudly instead of publishing sign-in links.

Keep the `COOKIE_SECRET` default only when a `NODE_ENV`/`APP_ENV` value says
development; make the schema require it otherwise, so a production boot with
no secret fails at `loadConfig`.

Add a `SECURE_COOKIES` (or derive it from a `PUBLIC_URL` scheme) to
`AppConfig`, read it in both cookie writers through `reply.server.config`, and
set `trustProxy` from config in `buildApp`.

Add `PUBLIC_URL` to `AppConfig` and build magic-link URLs from it, falling
back to the request origin only when it is unset.

One root plugin adding a fixed header set (`nosniff`,
`Referrer-Policy`, `X-Frame-Options`, a `default-src 'self'` CSP) is enough
for this app's needs; `@fastify/helmet` is a defensible dependency here since
the alternative is hand-maintaining the same list.

The `config` decorator is the right seam for all of the above — once
uploads dir, secure cookies, and `PUBLIC_URL` read through it, the decorator
stops being dead code.

Files expected to touch: `app/config.ts`, `app/app.ts`,
`app/views/partials/debug-alert.ejs`, `app/plugins/flash.ts`,
`app/plugins/identity.ts`, `app/sites/auth/request-origin.ts`,
`app/delivery/flash-magic-link-delivery.ts`.

Independent of BUG-002 through BUG-005; no ordering dependency, though it
touches `app/config.ts` which BUG-002 also extends (uploads dir) — coordinate
on that file if worked concurrently.

## Related work
- 06-tests-views.md — "The debug magic-link alert renders in production by default"
- 05-shell-ops.md — "`COOKIE_SECRET` ships a working default, so an unconfigured deployment has forgeable admin cookies"
- 05-shell-ops.md — "Cookies are never `secure`, and nothing knows whether it is behind TLS"
- 05-shell-ops.md — "Magic-link URLs are built from the `Host` header with no `trustProxy` and no allow-list"
- 05-shell-ops.md — "`config` is decorated on the instance and never read"
- 05-shell-ops.md — "No security headers on any response"
- FEAT-015 (implements the `mail` delivery this ticket's `Host`-header fix protects)

## Working

Re-validated every claim against the code before changing anything. All of it
still held: the partial had no guard and all three layouts included it
unconditionally, `COOKIE_SECRET` had a working default, no `secure` and no
`trustProxy` anywhere, `requestOrigin` was `request.protocol` + `request.host`,
and no response carried a security header. The one part that had moved on:
`config.uploadsDir` already existed and `app.ts` already read it (BUG-002), so
`config` was no longer wholly dead code — the rest of that observation stood.

### Changed

- `app/config.ts` — `NODE_ENV` parses into `environment`
  (`development` | `test` | `production`, default `development`); `PUBLIC_URL`
  is an optional URL reduced to its origin; `TRUST_PROXY` is a boolean.
  `COOKIE_SECRET` lost its schema default and now falls back to the
  development secret only outside production. `refuseUnsafeProduction` throws
  before the config is built when production has no `COOKIE_SECRET`, or has
  `MAGIC_LINK_DELIVERY=flash` — a delivery that prints the sign-in link into
  the page that asked for it. Three booleans are derived once and read
  everywhere: `secureCookies` (production, or an `https:` public url),
  `showsDebugMagicLinks` (not production, and the flash delivery), and
  `trustProxy`.
- `app/plugins/security-headers.ts` (new) — one root `onSend` hook adding
  `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy:
  strict-origin-when-cross-origin`, and a `default-src 'self'` CSP. No
  dependency: the list is four constants. `data:` is in `img-src` because a
  listing with no photograph renders `placeholderImageDataUri`, a base64
  `data:image/svg+xml` — verified in `core/listings/placeholder-image.ts` and
  asserted in the test. `style-src 'self'` and `script-src 'self'` need no
  relaxation: `grep` finds no `<script` and no `style="` in any of the
  templates.
- `app/app.ts` — `trustProxy: config.trustProxy` on the Fastify instance, and
  `addSecurityHeaders(app)` first among the root plugins.
- `app/plugins/identity.ts`, `app/plugins/flash.ts` — one line each:
  `secure: this.server.config.secureCookies` in the cookie options.
- `app/sites/auth/request-origin.ts` — returns `config.publicUrl` when set,
  the request's own origin otherwise. Kept the signature, so all six call
  sites (sign-in, checkout, the auth redirect, favorites, seller FAQs, admin
  moderation) get the configured origin without changing a line.
- `app/plugins/site-render.ts` — every page is handed
  `showsDebugMagicLinks`; the three layouts pass it into the partial, and
  `views/partials/debug-alert.ejs` renders only when it is true *and* a link
  is in the flash.
- `app/test/build-test-app.ts` — `TEST_CONFIG` gained the five new fields
  (`environment: 'test'`, no public url, no proxy, insecure cookies, debug
  links on) so the existing suite and the smoke test keep working unchanged.
- `README.md` — the Configuration table now carries all ten variables with
  what each decides, the two production refusals, and a Security headers
  section.

### Decisions

- **No `SECURE_COOKIES` variable.** The ticket offered it or deriving from
  `PUBLIC_URL`; `secureCookies` is derived (production or an `https:` public
  url). One fewer knob, and no way to configure a deployment into a state
  where it is served over TLS and says otherwise.
- **`MAGIC_LINK_DELIVERY` keeps its `flash` default.** The ticket suggested
  flipping it to `mail` so an unconfigured deployment fails loudly. It fails
  loudly now for the reason that matters — production refuses `flash`
  outright — and keeping the default preserves the zero-config development
  flow the Outcome asks for. Flipping it would break `make up` on a clone.
- **`showsDebugMagicLinks` reads `!isProduction && delivery === 'flash'`**,
  in that order, so both operands are reachable: production boots with the
  mail delivery and takes the first branch. The condition is belt-and-braces
  with the boot refusal, and this ordering keeps it testable rather than
  dead.
- **An unknown `NODE_ENV` is refused** rather than treated as development,
  matching how the other enums parse.
- `mail` still throws `NotImplementedError` — FEAT-015 replaces it with the
  outbox.

### Left alone

- `app/delivery/*` — selection did not change, so neither did the deliveries.
- `docker-compose.yml` — `NODE_ENV` defaults to `development` and
  `MAGIC_LINK_DELIVERY` to `flash`, so the dev flow needs no new variable.
- The `/uploads/` `setHeaders` nosniff in `app.ts` — now redundant with the
  root hook, but it belongs to BUG-002 and a static route setting its own
  header costs nothing.
- `app/plugins/flash.test.ts` needed a `config` decorator on its bare Fastify
  app, since `setFlash` now reads one; that is the only edit outside the
  files above.

### Tests

`npm run check` green: **1,382 tests, 0 failures**, 99.54% lines / 96.60%
branches. 1,362 before (a moving baseline — other tickets are landing tests
in the same tree). The 20 added:

- `config.test.ts` (+9) — every rule: the environment union, both production
  refusals, a production boot that succeeds, `showsDebugMagicLinks` per
  delivery, `secureCookies` across production and both public-url schemes,
  a public url reduced to its origin, a malformed public url, `TRUST_PROXY`.
- `plugins/security-headers.test.ts` (+4, new) — the full header set on a
  storefront page, on `/health`, on a 404, and the placeholder image the
  policy has to allow.
- `app.test.ts` (+5, new) — a production app renders no sign-in link even
  with one sitting in the flash (the flash delivery is deliberately still
  wired underneath, so the view guard is what is being tested), a development
  app still prints it, `Secure` on the identity and flash cookies in
  production and off in development, and a magic link built from `PUBLIC_URL`
  while the request carries `Host: attacker.example`.
- `sites/auth/request-origin.test.ts` (+1) — the public url wins over the
  host header.
- `plugins/flash.test.ts` (+1) — the flash cookie is `Secure` when the config
  says so.
