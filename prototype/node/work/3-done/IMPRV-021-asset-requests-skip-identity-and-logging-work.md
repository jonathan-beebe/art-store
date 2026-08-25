---
id: IMPRV-021
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-021: Asset requests skip identity and logging work; cookies unsign once

## Problem
`requestLog` and the root identity `preHandler` are registered before the static mounts (`app.ts:92-117`), and Fastify snapshots hooks per encapsulation context — so every `/app.css`, `/app.js`, and `/uploads/*` request runs the session-cookie parse/mint, unsigns all three identity cookies (HMAC-SHA256 each), allocates a pino child logger, and writes two JSON log lines (`request-log.ts:47-75,114-143`) to say "GET /app.css 200". Three asset fetches per page multiply request-hook volume and log noise several-fold.

`resolvePortalIdentities` (`plugins/identity.ts:105-108`) also runs `findSeller` + `findAdmin` row lookups on every request carrying those cookies — including storefront and auth pages that never use them. And across one page request the same identity cookies are HMAC-unsigned repeatedly (`identity.ts:147-171`, again in `plugins/unread-messages.ts:36`) — four to six verifications of the same values per request.

Side observation to verify while in here (correctness, not perf): `securityHeaders` registers after both static mounts (`app.ts:128`), so by the same hook-snapshot rule static responses may be missing those headers — check against `security-headers.test.ts`.

## Goal
An asset request costs static file service, and each identity cookie is verified once per request.

## Outcome
Asset requests trigger no identity queries and no per-request log lines (or one deliberate, minimal record if asset logging is wanted); a page request unsigns each identity cookie exactly once; portal identity lookups no longer run on sites that ignore them; page behavior and log semantics for real routes are unchanged.

## Why it matters
Assets are the majority of requests by count, and today each one pays cookie crypto, a logger allocation, and two log lines for zero information. The repeated HMAC work on page requests is a steady CPU tax with a single-verification answer. The customer identity hook is already site-scoped — this converges seller/admin resolution on the same shape.

## Discovery notes
- Registering the static mounts above the logging/identity hooks (or early-returning on a route-config marker) removes assets from the hook chain without touching real routes.
- Unsign each identity cookie once in one early hook and stash the parsed ids on the request; `identityId` and the unread hook read the stash.
- Portal lookups can resolve lazily or move into the seller/admin site plugins, mirroring the customer's site-scoped resolution.

## Related work
- IMPRV-013, IMPRV-014 (the other per-request costs)
- BUG-008 (static registrations last touched there)

## Working

2026-08-25 — re-validation against current code (line numbers in the ticket have drifted):

- `app.ts` today: `requestLog` at line 93 before the static mounts; `securityHeaders` and
  `identityCookies` at lines 164/166 after them.
- Probe (inject against `buildLoggedTestApp`, run in the container, before any change):
  - `/app.css`, `/app.js`, and a missing `/uploads/*` file all carry **all four security
    headers today**, plus `x-request-id`, a minted `sid` cookie, and a full
    `http.request will`/`did` pair each.
  - Reason: `@fastify/static` wraps itself in `fastify-plugin` (skip-override), so both
    static mounts register their routes on the **root context**. Root hooks apply to root
    routes regardless of registration order. The ticket's hook-snapshot premise does not
    hold for these mounts.
- Consequences:
  - securityHeaders verification item: static responses are NOT missing the headers.
    No reordering; instead a pinning test locks the headers onto `/app.css` and the
    hashed variant so the asset-skip change cannot regress them.
  - Registration order cannot exempt assets from the hooks. The skip is an early return
    inside `requestLog`'s hooks on a pure asset-path predicate
    (`/app.css`, `/app.js`, `HASHED_ASSET_NAME` basenames, `/uploads/*`).
- `resolvePortalIdentities` (root preHandler) runs on every request today — assets,
  `/health`, magic-link, storefront — and `findSeller`/`findAdmin` query when cookies
  are present. Moves site-scoped: seller portal and admin site each add their own
  preHandler, mirroring the customer's site-scoped resolution.
- Cookie unsign count measured before the change: 6 `unsignCookie` calls for a storefront
  page request carrying a seller cookie. Fix: memoized parsed-actor-id stash on the
  request; `identityId` fills and reads it, so each identity cookie unsigns at most once.
- Deliberate behavior deltas (allowed by the ticket's outcome):
  - Asset responses drop logging entirely: no `will`/`did` lines, no `x-request-id`
    header, no `sid` mint on an asset-only first request. Page requests unchanged.
  - Missing `/uploads/*` files 404 unlogged (they match the asset predicate). Page-route
    misses like `/nothing-here` keep full logging.
  - `/health`, magic-link, and storefront requests stop running seller/admin row lookups.
  - Identity behavior (who is signed in where) unchanged: portals resolve their own
    actor before their layouts, guards, and unread hooks run.

2026-08-25 — resolution:

- `isAssetPath` in `http/asset-manifest.ts`: `/app.css`, `/app.js`, a root-level
  `HASHED_ASSET_NAME` basename, or a `/uploads/` prefix. `request-log.ts` early-returns
  on it in both hooks; `pathnameOf` strips query strings at the call sites.
- `identity.ts`: `parsedActorIds` stash decorated `null`, lazily created per request;
  `identityId` reads/fills it (`in` check distinguishes a stashed null from an absent
  key), so each identity cookie unsigns at most once per request.
- Root `resolvePortalIdentities` removed; `resolveSellerIdentity`/`resolveAdminIdentity`
  exported and registered at the top of `sites/seller/index.ts` and `sites/admin/index.ts`
  before `countUnreadMessages`, so sign-in routes, guards, layouts, the not-found
  catchall, and the events route all still see their actor.
- Tests: 5 new-behavior tests (asset silence, unsign-once, no off-portal lookups),
  5 pinning tests (page-miss logging, security headers on static, portal account pages),
  2 `isAssetPath` unit tests. 1993 -> 2005 tests, `make check` green,
  coverage 99.42/95.84/99.38 (gate 95/90).
- Reviewer: accept, no blocking defects. Noted, left for later: `isAssetPath` ignores
  the HTTP method (a `POST /app.css` also goes unlogged), and a URL-encoded spelling
  like `/app%2Ecss` is served but logged as a page.
