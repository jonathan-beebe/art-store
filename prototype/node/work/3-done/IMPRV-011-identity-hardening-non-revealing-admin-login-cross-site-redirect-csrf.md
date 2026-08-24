---
id: IMPRV-011
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-011: Identity hardening — non-revealing admin login, cross-site redirect refusal, CSRF tokens

## Problem
`adminSite` passes `admits` to `signInRoutes`, which refuses before issuing a link, so the response reveals which addresses are operators (PHP answers byte-identically either way). `resolveLocalRedirect` checks origin only, so a seller-site link can `redirect_to` an admin path (PHP `ActorType::allowsPath()` refuses cross-site paths). No POST carries a CSRF token; the defence is `SameSite=Lax` plus CSP `form-action 'self'`, recorded nowhere. `docs/alignment.md` §7 decision 3 adopts tokens.

## Goal
Node's sign-in and form posts are as hard to abuse as the other two prototypes'.

## Outcome
The admin sign-in POST answers the same page and status for an admitted and an unknown address, issuing nothing for the unknown one, with the debug bar's local-dev notice never naming a seeded address; a `redirect_to` outside the signing-in site's own path prefix is refused; every POST form carries a CSRF token that the server verifies, a missing or wrong token answers 403 with the site's page, and tests cover each; `docs/identity.md` states all three.

## Why it matters
Operator addresses are personal data; a cross-site redirect is a phishing hop; the CSRF defence should be a decision the docs can point at.

## Discovery notes
A double-submit cookie signed with the existing `COOKIE_SECRET` needs no dependency; the token is a hidden input rendered by the layout. PHP's `allowsPath` and its byte-identical response are the shapes to borrow (`prototype/php` on `main`).

## Related work
- BUG-004, BUG-006
- prototype/php IMPRV-002

## Working

**Problem-statement correction (confirmed).** The debug bar
(`app/views/partials/debug-alert.ejs`, gated by `showsDebugMagicLinks`) prints
only the magic-link URL — it never names a seeded address. Seeded addresses
appear only in `README.md`'s table and `app/db/seed-admins.ts`. No code
change was needed for that clause; it was already true.

### Non-revealing admin sign-in
- `SignInRoutesOptions.refusal` is gone. `admits` refusing an address now
  answers exactly what admitting it would have: the same `notice` flash
  (`Sign-in link sent to <email>.`), the same redirect, no `alert`. Only
  `adminSite` passes `admits`, and it no longer passes a refusal string —
  there is nowhere left for one to go.
- The refused branch writes one server-side line, `magic_link.request`
  `refused` at `info`, `data: {reason: 'not_admitted', actor_type, email:
  redactedEmail(email)}` (`app/core/auth/email-address.ts`, a sha256 digest
  mirroring `redactedRateLimitKey`'s shape) — never the raw address, per
  `docs/alignment.md` §2.1.
- **Decision: `admits` still short-circuits before `sendMagicLink`; timing is
  not equalised.** An admitted request does one extra `INSERT` and a delivery
  call a refused one never does. Closing that channel means constant dummy
  work on every refusal — machinery a prototype's sign-in page does not
  warrant, and PHP's own non-revealing test (`IMPRV-002`) checks response
  bodies only, not timing. Recorded in `docs/identity.md`.
- **Accepted, not fixed:** under `MAGIC_LINK_DELIVERY=flash` (dev only), an
  admitted address's page carries a debug link and a refused one's does not —
  there is no link to print for a refusal. `showsDebugMagicLinks` is false
  outside development and production already refuses `flash` delivery, so
  this never reaches a real deployment. The byte-identical test
  (`sign-in-routes.test.ts`) runs under `outboxMagicLinkDelivery`, matching
  PHP's own test, which asserts under `mail` delivery for the same reason.

### Cross-site redirect refusal
- `allowsPath(actorType, path)` (`app/core/auth/local-redirect.ts`), a pure
  lookup mirroring PHP's `ActorType::allowsPath` exactly: seller refuses
  `/admin*`; customer refuses `/seller*` and `/admin*`; admin refuses
  `/seller*`. (`*` meaning the exact prefix or anything under it.)
- `keepLocalRedirect`/`resolveLocalRedirect` now take `actorType` alongside
  `origin` and refuse a target `allowsPath` refuses, on top of the existing
  control-character/`//`/`/\\`/foreign-host checks. Every call site already
  had the actor in scope — no new plumbing needed at any of them
  (`sign-in-routes.ts`, `sites/auth/index.ts`, `moderation.ts`,
  `fulfillments.ts`, `faqs.ts`, `favorites.ts`).
- Checked at both ends: the sign-in form/link request (so a crafted
  `redirect_to` never reaches `magic_links`) and again on
  `GET /auth/magic/:token` consumption (so a row that reached the table some
  other way still cannot carry a visitor cross-site) — a dedicated test
  crafts such a row directly to prove the second check is real, not just
  reachable through the first.

### CSRF tokens
- **Decision: double-submit, derived from the existing `sid` cookie, not a
  synchronizer token in a session store.** `csrfToken(sessionId, secret) =
  HMAC-SHA256(secret, sessionId)` (`app/core/security/csrf-token.ts`), keyed
  by `COOKIE_SECRET` (the same secret already signing the identity and flash
  cookies) and the browser's `sid` (`app/plugins/request-log.ts`, one per
  browser already, unsigned, a year long). No new cookie, no session store —
  the whole defence rides the cookie IMPRV-009 already added.
- The token is rendered as a hidden `_csrf_token` field by a shared partial
  (`app/views/partials/csrf-field.ejs`), included from all 44 `<form
  method="post">` tags this app has (29 view files — GET forms, e.g. the
  storefront search box and the admin/payouts filter, are untouched).
  `addSiteRender` (`app/plugins/site-render.ts`) adds `csrfToken:
  csrfTokenForRequest(request)` to every page's data, the same way it already
  adds `flash` and `identity`, so a page template never has to compute it.
- **Decision: verified in one `preValidation` hook per site, not 36
  route-level schemas.** `submittedForm`/`listingFormBody` strip unknown
  fields, so a route whose schema forgot the field would fail open if
  checked any later than `preValidation` (before Fastify's own schema
  validation runs, which is what strips it). `csrfProtection`
  (`app/plugins/csrf.ts`) is that hook: missing, foreign, or wrong token →
  403 in the requesting site's own layout (`errorPageView`'s new `FORBIDDEN`
  branch, `app/plugins/error-pages.ts`).
- **Deviation from the plan: registered inside each site
  (`admin`/`seller`/`shop`), not once at the root.** `@fastify/multipart`'s
  `attachFieldsToBody` (seller's image upload) populates `request.body`
  through a `preValidation` hook of its own; a hook the root adds always runs
  ahead of one a child registers, regardless of source order, so a root
  registration ran before multipart had attached anything — every seller
  upload 403'd with no field to find. Registered inside each site after that
  site's own body parser (`portal.register(csrfProtection)` after
  `portal.register(multipart, ...)` in `sites/seller/index.ts`), it runs
  once that parser's own hook, where one exists, already has. Confirmed by a
  failing integration test before the fix, passing after.
- **Allowlist (`CSRF_EXEMPT` in `app/plugins/csrf.ts`): empty.** Every
  state-changing route this app registers (POST only — no PUT/PATCH/DELETE
  exist) is a plain HTML form submission; none is a webhook or a
  browser-to-API call with nowhere to carry a hidden field. `csrf.test.ts`'s
  completeness test reads the app's own route table via Fastify's `onRoute`
  hook (not a hand-kept list) and probes every state-changing route with no
  token, asserting 403 unless the allowlist names it — closing over any
  route added later the way `customer-owned-tables-manifest.test.ts` closes
  over the schema.
- **Test infrastructure:** `buildTestApp`'s `app.inject` is wrapped
  (`withAutomaticCsrfToken`, `app/test/build-test-app.ts`) to attach a valid
  token and a stable default `sid` to a state-changing call automatically —
  object payloads, raw urlencoded strings, and hand-built multipart buffers
  (splicing a field in ahead of the closing boundary) all handled — so the
  hundreds of pre-existing POST tests needed no per-call changes. `rawInject`
  (the pre-wrap `app.inject`) is exposed on `TestApp` for `csrf.test.ts`'s
  own tests of the guard's failure paths, and for two page-body-equality
  tests (`admin/routes/orders.test.ts`, `admin/routes/fulfillments.test.ts`)
  that needed a shared explicit `sid` so both compared pages derived the same
  token. One real HTTP test (`events.test.ts`, a live `fetch()` that bypasses
  `app.inject` entirely) carries its own `sid` and computed token by hand.

### make check
1827 -> 1887 tests. Coverage 99.50/97.28/99.55 -> 99.50/96.76/99.56. The
branch dip is two small, deliberately-defensive, currently-unreachable
branches: `csrf.ts`'s `sessionId !== null` (false only before
`requestLog`'s `onRequest` has run, which precedes every `preValidation`
hook including this one) and `error-pages.ts`'s `answerForbidden` plain-text
fallback (only reachable for a state-changing route with no site layout;
none exists today — the same defensive shape `errorPages`' own handler and
`rate-limit.ts`'s `answerIfRateLimited` already carry). Both accepted rather
than forced with a contrived test. `make check`, `make smoke`, `make routes`
all green.

### What PHP and Rails should match
- The non-revealing shape: same flash key, same copy, same redirect,
  whether or not an address is admitted.
- `allowsPath`'s per-actor semantics (seller ⊄ admin, admin ⊄ seller,
  customer ⊄ either) — Node's mirrors PHP's `ActorType::allowsPath` exactly;
  worth confirming Rails' admin/seller/customer redirect handling refuses
  the same way.
- The CSRF decision itself: Node's is double-submit derived from an existing
  per-browser cookie; PHP already had Laravel's synchronizer token
  (`@csrf`/`VerifyCsrfToken`) for free, unrelated to this ticket; Rails has
  its own built-in equivalent. No cross-stack shape to align beyond
  "every POST form carries one, checked before validation, and a rejection
  answers with the site's own page" — Node answers 403; PHP's stock behaviour
  is 419. Worth a decision at the contract level if the three should agree
  on one status code, but out of this ticket's scope.
