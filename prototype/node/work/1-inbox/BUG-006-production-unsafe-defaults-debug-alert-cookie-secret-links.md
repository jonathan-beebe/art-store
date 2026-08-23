---
id: BUG-006
type: bug
status: open
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
