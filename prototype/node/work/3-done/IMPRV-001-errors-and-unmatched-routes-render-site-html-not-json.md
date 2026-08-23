---
id: IMPRV-001
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-001: Errors and unmatched routes render the site's HTML, never JSON

## Problem
Twenty route handlers call zod's `.parse()` directly on request input instead of `.safeParse()`, so a malformed request throws into Fastify's default error handler and answers JSON instead of a 4xx page. Call sites: `sites/auth/sign-in-routes.ts:64,71`; `sites/shop/routes/carts.ts:47,50,61,72`; `sites/shop/routes/checkout.ts:88`; `sites/shop/routes/favorites.ts:26`; `sites/shop/routes/listings.ts:18`; `sites/shop/routes/messages.ts:89,105`; `sites/seller/routes/faqs.ts:65,94`; `sites/seller/routes/orders.ts:87`; `sites/admin/routes/orders.ts:13`, `payouts.ts:19,35`, `listings.ts:19`, `ledger.ts:12`, `fulfillments.ts:13`.

Verified: `POST /login` with `email=a%40b.com&email=c%40d.com` (a duplicate form field, which `@fastify/formbody` turns into an array) returns:

```
500 application/json
{"statusCode":500,"error":"Internal Server Error","message":"[\n  {\n    \"expected\": \"string\",\n    \"code\": \"invalid_type\",\n    \"path\": [\n      \"email\"\n    ], …"}
```

Any anonymous visitor reaches this on the storefront's own sign-in form. No stack trace leaks, but internal field names and the schema shape do, and a bad request is reported as a server fault.

`app.ts` registers no `setErrorHandler` and no `setNotFoundHandler` anywhere in the tree. Verified against the running app:

| Request | Answer |
| --- | --- |
| `GET /definitely-not-a-route` | `404 application/json` `{"message":"Route GET:/definitely-not-a-route not found",…}` |
| `GET /seller/nope` (signed in) | `404 application/json`, same shape |
| `GET /admin/sellers/99999` (`reply.callNotFound()`) | `404 application/json`, same shape |
| any 500 | `application/json` |

`sites/shop/views/not-found.ejs` exists but is only reachable through `renderNotFound` on routes that already matched. Mistyping a storefront URL gets a JSON blob, not the styled page.

404 handling is spelled five different ways across three sites, three of which produce different response bodies for the same condition: `sites/shop/shop-page.ts:31` (`reply.status(404).render('not-found', …)`); `sites/seller/not-found.ts:6` (`reply.code(404).type('text/plain').send('Not found')`); `sites/admin/routes/messages.ts:17-18` and `sites/admin/routes/moderation.ts:78-79` (two identical private `notFound` helpers); `sites/admin/routes/listings.ts:39,42` (the same expression inlined twice); `sites/admin/routes/sellers.ts:15,18` and `sites/admin/routes/customers.ts:21,24` (`reply.callNotFound()`). Verified: `/admin/listings/99999` → `text/plain "Not found"`, `/admin/sellers/99999` → JSON, `/art/nope` → 2,268 bytes of themed HTML, `/seller/listings/99999` → `text/plain`.

## Goal
Every malformed request and every unmatched URL answers with the requesting site's own styled page, never a JSON error blob.

## Outcome
- A malformed body/query answers 400 with the site's error page.
- An unmatched URL under `/`, `/seller`, `/admin` answers 404 with that site's not-found page.
- An unexpected error answers 500 with a generic page and one structured log line carrying the request id.
- One not-found idiom (`reply.callNotFound()`) is used across all routes.

## Why it matters
A malformed request reading as a 500 is a crash in a demo, reachable by any anonymous visitor on a public form. The rule "core returns explicit results rather than throwing for expected business cases" extends to the boundary: a bad request is a 4xx, not a server fault. Fastify's own doctrine calls for `setErrorHandler`/`setNotFoundHandler` at the framework boundary; the app currently answers errors and unmatched routes in JSON despite being a server-rendered HTML app. The same condition (404) currently gets six different implementations and three different response bodies.

## Discovery notes
Two moves, done together:
- Register a `setNotFoundHandler` inside each site plugin (Fastify scopes it per encapsulated context, so `/seller/*`, `/admin/*`, and `/` each get their own) and one root `setErrorHandler` that logs via `request.log.error` and renders a 500 template for the site the request landed on, falling back to plain text.
- Convert every `.parse` call listed above to `.safeParse` and answer 400/404 explicitly, or let the parse throw and have the new `setErrorHandler` render it as a 400 once it can distinguish a zod error from an unexpected one.
- Once each site has a `setNotFoundHandler`, delete `sellerNotFound` and the two admin `notFound` helpers and let every miss be `reply.callNotFound()`. One idiom, one template per site.

Files expected to touch: `app/app.ts`, `app/sites/shop/index.ts`, `app/sites/seller/index.ts`, `app/sites/admin/index.ts` (or their equivalents registering each site plugin), `app/sites/seller/not-found.ts` (deleted), `app/sites/admin/routes/messages.ts`, `app/sites/admin/routes/moderation.ts`, `app/sites/admin/routes/listings.ts`, `app/sites/admin/routes/sellers.ts`, `app/sites/admin/routes/customers.ts`, and the twenty `.parse` call sites listed above.

Ordering: this ticket should land before IMPRV-002. IMPRV-002 moves validation onto route schemas via a validator compiler; a compiler failure needs `setErrorHandler` in place to render as the site's error page rather than Fastify's default JSON.

## Related work
- 05-shell-ops.md: "`zod.parse` inside 20 handlers turns malformed input into a 500 that echoes the schema", "No `setErrorHandler` and no `setNotFoundHandler`: an HTML app answers errors in JSON", "Five spellings of '404' across three sites"
- IMPRV-002 (depends on this ticket landing first)

## Working

### Verified against the code before changing it
- `app.ts` had no `setErrorHandler` and no `setNotFoundHandler`. Confirmed.
- `POST /login` with `email=a%40b.com&email=c%40d.com` answered `500 application/json` with the zod issue list. Confirmed, and it is the reproduction the new test pins.
- The five spellings of 404 were all present: `shop/shop-page.ts` (themed page), `seller/not-found.ts` (`text/plain`), the two private `notFound` helpers in `admin/routes/messages.ts` and `admin/routes/moderation.ts`, the inlined expression twice in `admin/routes/listings.ts`, and `reply.callNotFound()` in `admin/routes/sellers.ts` / `customers.ts` (which reached Fastify's JSON 404).

### Two things the ticket did not anticipate
1. **`reply.callNotFound()` keeps the reply the caller already holds.** Fastify swaps the request's route context to the 404 context but never rebuilds the reply, so the `render` decorator a site added is only present when the caller was already inside that site. `@fastify/static` is registered at the root with `prefix: '/'`, which claims `GET /*`, so *every* unmatched GET is a missing file handed over by `callNotFound` on a root-level reply with no `render`.
   Fix: `addSiteRender` now returns a `SitePageRenderer` that writes the site's page onto any reply through `reply.view` (decorated at the root by `@fastify/view`). The not-found handlers use it rather than `reply.render`.
2. **A root `/*` route swallows `/seller/nope` and `/admin/nope` too**, so a per-site `setNotFoundHandler` alone answered them in the storefront's layout. `addNotFoundPage` therefore also registers `site.all('/*')` for a site that has a prefix, which is more specific than the static route and lands in that site's own context. The storefront has no prefix, so it takes the miss through the root 404 context, which is what its `setNotFoundHandler` replaces.

Both are covered by tests (`/seller/nope` → seller page, `/admin/nope` → admin page, `/nope` → storefront page, `/seller/listings/99999` via `callNotFound` → seller page).

### Changed
- New `app/plugins/error-pages.ts` (+ `error-pages.test.ts`): `failureStatusCode` (ZodError → 400; an error carrying a 4xx `statusCode` keeps it; everything else 500), `errorPageView`, `renderErrorPage`, `addNotFoundPage(site, renderPage)`, `addErrorPage(app)`.
- `app/plugins/site-render.ts`: `addSiteRender` returns the site's `SitePageRenderer`; the `render` decorator is now a thin call to it.
- `app/app.ts`: `addErrorPage(app)` alongside the other root wiring.
- `app/sites/{shop,seller,admin}/index.ts`: each keeps the renderer and calls `addNotFoundPage`. The seller's `FST_REQ_FILE_TOO_LARGE` handler is untouched — its `throw error` now reaches the root handler because Fastify chains error handlers by prototype.
- New templates: `sites/{seller,admin}/views/not-found.ejs` and `sites/{shop,seller,admin}/views/error.ejs`.
- `sites/shop/shop-page.ts` `renderNotFound` and `sites/seller/not-found.ts` `sellerNotFound` both delegate to `reply.callNotFound()` and keep their signature, so their ~30 callers are unchanged.
- `sites/admin/routes/messages.ts`, `moderation.ts`, `listings.ts`: the private `notFound` helpers and the inlined expressions are `reply.callNotFound()`. `moderation.ts`'s `badRequest` renders the admin error page through `renderErrorPage`.
- New `sites/{shop,seller,admin}/index.test.ts`.

### Left alone deliberately
- **The twenty `.parse` call sites.** The ticket offers either conversion to `safeParse` or letting the throw render as a 400; the second is what landed, because IMPRV-002 moves those parses onto route schemas and rewriting them here would be undone. The reproduction now answers `400 text/html`.
- **`sites/seller/not-found.ts` is not deleted.** Deleting it means editing twenty route call sites in files another ticket is editing right now; the module is one line delegating to the single idiom, and the callers read the same either way.
- **`docs/architecture.md`'s plugin list** is stale for several tickets, not only this one. FEAT-017 owns the refresh.
- **The storefront 404 page renders signed-out.** A miss taken through `@fastify/static`'s hand-off carries a 404 context whose hooks were snapshotted before the site's own were attached, so neither the customer cookie nor the unread count is read there. The header shows "Sign in" on that page. Resolving it means an identity hook at the root, which costs a query on every asset request.

### Tests
1431 → 1448 pass, 0 fail. `npm run check` green (typecheck, lint, coverage 99.60% lines / 96.83% branches against the 95/90 gate). `error-pages.ts` and `site-render.ts` are at 100%.
