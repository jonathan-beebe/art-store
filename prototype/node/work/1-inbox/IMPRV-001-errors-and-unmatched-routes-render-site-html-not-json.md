---
id: IMPRV-001
type: improvement
status: open
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
