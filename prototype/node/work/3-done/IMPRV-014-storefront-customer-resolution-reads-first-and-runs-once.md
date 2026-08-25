---
id: IMPRV-014
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-014: Storefront customer resolution reads first and runs once

## Problem
`resolveCurrentCustomer` (`app/actions/customers/resolve-current-customer.ts:15-24`) always runs inside `runInTransaction`, and the dialect's `beginTransaction` is unconditionally `begin immediate` (`app/db/node-sqlite-dialect.ts:80-85`). It runs as a `preHandler` on every storefront route (`plugins/identity.ts:178-184`), so every storefront GET — including a returning visitor's read-only home page — acquires SQLite's single write lock to read an existing customer row. The insert branch (`createAnonymousCustomer`) only fires on a first-ever visit. Under concurrency this serializes the hottest read path against checkout, page-view upserts, and rate-limit upserts, forfeiting WAL's readers-don't-block-writers property.

The customer is also resolved twice per GET: the shop-level `countUnreadMessages('customer')` hook (`sites/shop/index.ts:16`) runs before the storefront-level `resolveCustomerIdentity` (`sites/shop/storefront.ts:22`), so `plugins/unread-messages.ts:31-40` finds `request.currentCustomer === null` and does its own `resolveCustomerFromCookie` — the code at `:32-34` already tries to reuse the request's customer; the hook order defeats it.

`currentCart` (`app/actions/carts/current-cart.ts:14-24`) has the same shape: `BEGIN IMMEDIATE` around a lookup that almost always hits.

## Goal
Storefront reads never take the write lock, and identity resolves once per request.

## Outcome
A returning visitor's GET opens no write transaction and resolves the customer cookie exactly once; a first visit still creates exactly one customer row with the same cookie behavior; the per-request statement count drops accordingly and the suite stays green.

## Why it matters
Two of the storefront's write-lock acquisitions per GET exist to serve reads. The measured per-request budget today is roughly 12–14 statements with 2 write-lock acquisitions for a typical signed-in storefront page; this ticket and IMPRV-013 together bring that to a handful of cheap reads. Lock contention is the first thing to fall over under concurrent demo traffic.

## Discovery notes
- Read-first: do the cookie lookup outside any transaction, and enter the transaction only for the create-anonymous miss branch. Same restructure applies to `currentCart`.
- For the duplicate resolve: register the unread hook inside `storefrontRoutes` after `resolveCustomerIdentity`, or let the unread hook skip when the route will resolve the customer anyway — its reuse branch already exists.
- Related observation, deliberately out of scope: a cookie-less request (every crawler hit) mints an anonymous `customers` row, and `customerRows` (`sites/admin/queries/customer-rows.ts`) later loads all of them. Whether to mint lazily or sweep orphaned anonymous rows is a behavior decision worth its own scoping pass.

## Related work
- FEAT-002 (magic-link identity, anonymous merge)
- IMPRV-013 (the other half of the per-request budget)

## Working

2026-08-25 — re-validated on `node/performance` (after IMPRV-013, 8f309a7):

- `resolve-current-customer.ts:19-23` still wraps the cookie read in
  `runInTransaction`; `node-sqlite-dialect.ts:80-85` still `begin immediate`.
- `current-cart.ts:14-35` same shape.
- Hook order still defeats the reuse branch: `sites/shop/index.ts:16` registers
  `countUnreadMessages('customer')` at shop level, so it runs before both
  `resolveCustomerIdentity` (`storefront.ts:22`) and `rememberCustomerIdentity`
  (`sign-in-routes.ts:71`); `unread-messages.ts:31-40` falls back to its own
  `resolveCustomerFromCookie` on every request. IMPRV-013 changed only the
  count query (`unreadMessageCount` is one COUNT); the interplay is unchanged.
- `plugins/events.ts:144` already reads `request.currentCustomer` directly —
  no fallback to retire there.
- Shop 404 renders through `setNotFoundHandler` with no preHandler options, so
  the shop-level unread hook never ran there; moving the hook changes nothing
  on the 404 page.
- `carts.ts:82` calls `currentCart(transacted, …)`; read-first still joins the
  caller's transaction because the read runs on the context's own `db`.

Plan:
1. `resolveCurrentCustomer`: cookie read on the plain context, transaction
   only around the `createAnonymousCustomer` miss branch.
2. `currentCart`: same restructure — select outside, insert inside
   `runInTransaction`.
3. Unread hook runs after identity: drop the shop-level registration; add
   `countUnreadMessages('customer')` in `storefrontRoutes` after
   `resolveCustomerIdentity`, and in `signInPages`' customer branch after
   `rememberCustomerIdentity`. `SITE_ACTORS.customer` then reads
   `request.currentCustomer` like the seller/admin resolvers; the cookie
   fallback goes away.

Tests (TDD):
- Action level: with a rival `node:sqlite` connection holding
  `begin immediate` on a file-backed database, a remembered customer and an
  existing cart still resolve (fails today with SQLITE_BUSY after the 5s
  busy timeout).
- Plugin level: a Kysely `log` override counts statements; a storefront GET
  and a /login GET each touch `customer_merges` exactly once (today: twice).

Resolution, 2026-08-25:

- `resolveCurrentCustomer` reads the cookie'd customer on the plain context and
  enters `runInTransaction` only for the `createAnonymousCustomer` miss branch.
- `currentCart` reads on `context.db` and wraps only the insert-on-miss;
  `carts.ts:82`'s transacted caller still joins its own transaction.
- `countUnreadMessages('customer')` moved out of the shop scope into
  `storefrontRoutes` (after `resolveCustomerIdentity`) and `signInPages`'
  customer branch (after `rememberCustomerIdentity`); `SITE_ACTORS.customer`
  reads `request.currentCustomer` like the seller/admin resolvers, and the
  cookie fallback is gone.
- Four new tests: a remembered customer and an existing cart resolve while a
  rival connection holds the write lock (file-backed db, `begin immediate` on
  a raw `node:sqlite` connection); a storefront GET and a /login GET each
  touch `customer_merges` exactly once (Kysely `log` statement capture).
- `make check` green: 1929 tests (baseline 1925 + 4), coverage 99.43% lines /
  95.89% branches — unchanged from baseline.
- The 404 page renders through `setNotFoundHandler`, which runs no preHandler
  hooks, so it carried no badge before and carries none now.
- Deferred, per the ticket: lazy minting / sweeping of crawler-created
  anonymous rows.
