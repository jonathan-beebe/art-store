---
id: FEAT-004
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-004: Seller portal — listings, activity, fulfillments, earnings, notifications

## Problem
`/seller` is a placeholder. Sellers need the back-office the brief describes: create and manage listings, see activity on them (views, favorites, cart adds), see what sold and ship it, and read earnings and payouts.

## Goal
An artist signs in, lists a piece, marks it for sale, and later ships the sale and reads what they are owed — all from `/seller`, in a plain tool-focused UI.

## Outcome
- Dashboard at `/seller`: listing counts by status, recent activity, open fulfillments, available and held balance, unread notifications.
- Listings: index, new, edit, show; image upload; status buttons that follow the core transitions and refuse illegal ones with a message; a removed listing shows the admin's removal kind and reason and cannot be put back on sale by the seller.
- Listing activity page: totals and a 14-day daily breakdown of views / favorites / cart adds.
- Fulfillments ("Orders" in the nav, "fulfillment" in copy): grouped by status; detail page with the customer's shipping address and a mark-shipped form (carrier + tracking) that notifies the customer.
- Earnings: balance (held / available / paid out), sold-goods report, payout history. No payout button — the run is an admin action.
- Notifications inbox with mark-as-read.
- Every page is behind `requireSeller`; another seller's listing or fulfillment id answers 404 on reads and writes.
- Each page has an integration test; listing status refusal and cross-seller 404s are tested.

## Why it matters
This is half the product and the half the showdown's reviewers will click through first.

## Discovery notes
Reference: `prototype/rails/src/app/controllers/seller/**`, `app/views/seller/**`, `app/domain/reports/**`. Stock Tailwind, system font, semantic HTML, vanilla controls — tables, forms, buttons; no component library.
- Routes parse bodies with zod and call FEAT-003 actions; a report needs a pure function under `app/core/reports/` (activity totals, daily timeline, status tally, payout summary) fed rows by the route.
- Prices are entered in dollars and stored in cents (`parseDollars`); show field errors next to fields.
- Multipart upload via `@fastify/multipart`; accept png/jpg/webp; write to `public/uploads`.
- Touch only `app/sites/seller/**`, `app/core/reports/**`, and one registration line in `app/app.ts`. FEAT-005 / FEAT-006 / FEAT-008 run in parallel in the same tree — commit with an explicit pathspec.

## Related work
- `prototype/rails/work/3-done/FEAT-004-seller-portal.md`
- `__local__/retro.md` item 10 (say "fulfillment" where the row is one).

## Working

### Routes (all behind `requireSeller`, all under `/seller`)

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/` | Dashboard: listing status tally, awaiting-shipment count, escrow balance, unread count, 5 most recent notifications. |
| GET | `/listings` | Index: every listing with per-listing activity totals, price, status, and status buttons. |
| GET | `/listings/new` | Blank listing form. |
| POST | `/listings` | Create (multipart) → draft; 422 re-renders the form with field errors on invalid input. |
| GET | `/listings/:id` | Activity page: totals, 14-day daily breakdown, sales, removal banner if any. |
| GET | `/listings/:id/edit` | Edit form filled from the listing. |
| POST | `/listings/:id` | Update (multipart); 422 on invalid input; image omitted keeps the one on file. |
| POST | `/listings/:id/status` | Status change; refuses (flash + redirect) an illegal lifecycle move or a return to `for_sale` while removed. |
| GET | `/orders` | Fulfillments grouped by status (`awaiting_shipment`, `shipped`, `delivered`). |
| GET | `/orders/:id` | One fulfillment: shipping address, the seller's own items, net, mark-shipped form or shipment record. |
| POST | `/orders/:id/ship` | Marks shipped (carrier + tracking); refuses an incomplete or already-departed shipment. |
| GET | `/earnings` | Balance (held/available/paid out), sold-goods table, payout history. No payout control. |
| GET | `/notifications` | Inbox, newest first, unread badge + mark-read form. |
| POST | `/notifications/:id/read` | Marks one notification read. |

A listing or fulfillment id naming another seller's row, or a non-numeric id, answers 404 on every route above (read and write).

### What exists

- `app/core/reports/`: `status-label.ts` (`statusLabel`), `listing-status-tally.ts` (`listingStatusTally`, `totalListings`), `activity-totals.ts` (`activityTotals`, `totalActivity`), `activity-timeline.ts` (`activityTimeline`) — pure, sidecar-tested, ported from the Rails `Domain::Reports` modules with camelCase names.
- `app/sites/seller/current-seller.ts` — `currentSellerId(request)`, the one place every route reads the guarded seller's id.
- `app/sites/seller/format.ts` — `formatDay`, `formatDate`, `formatDateTime`, `dollarsInputValue`; no `Intl` dependency, so it renders identically regardless of ICU data.
- `app/sites/seller/listing-transitions.ts` — `sellerListingTransitions(status, hasActiveRemoval)`: the core's transition table with `for_sale` filtered out under an active removal. Feeds both the status buttons and the status-change route's refusal.
- `app/sites/seller/listing-form.ts` — reads `@fastify/multipart`'s `attachFieldsToBody: true` shape into `ListingDraftFields`; `uploadedImagePart` tells a real upload from an untouched `<input type=file>` (empty filename).
- `app/sites/seller/listing-image-upload.ts` — `saveUploadedListingImage` writes to `public/uploads/<uuid>.<ext>` and returns the `/uploads/...` path the `imagePath` column stores.
- `app/sites/seller/not-found.ts`, `params.ts` — `sellerNotFound(reply)`, `parseIdParam(request.params)` (zod-coerced, null on anything not a positive integer).
- `app/sites/seller/queries/` — Kysely reads with no domain logic: `listings.ts`, `listing-activity.ts`, `fulfillments.ts`, `payouts.ts`, `notifications.ts`.
- `app/sites/seller/test-fixtures.ts` — `createTestListing`, `createForSaleListing`, `createFulfillment`, `createDeliveredFulfillment`, `createTestNotification`: builds fixtures through the real FEAT-002/003 actions (cart → order → `finalizeOrder` with `4242...` → escrow held) rather than inserting rows directly, so a fixture can't drift from what the actions actually write.
- `app/sites/seller/routes/{home,listings,orders,earnings,notifications}.ts` + views under `app/sites/seller/views/{listings,orders,earnings,notifications}/` and `views/partials/{field-error,status-buttons}.ejs`.

### Decisions

- **Illegal transitions redirect with a flash alert, not a 422 render.** The Rails reference renders a `refused` page; this codebase already established post-redirect-get for refusals in FEAT-002 (`auth/index.ts`'s bad-address case), so the status-change and mark-shipped routes follow that, not Rails.
- **Field-level validation (listing create/update) still renders in place at 422**, echoing the entered values — matches the FEAT-003 `listingDraftErrors` contract and lets "next to the field" work, which a redirect-and-flash can't do (the flash cookie carries only strings, not a field map).
- **The removal-aware `for_sale` block lives in `app/sites/seller/listing-transitions.ts`, not in `app/core/listings/`.** The ticket's touch-list excludes `app/core/listings/**`; the rule is a single boolean (`hasActiveRemoval && status === 'for_sale'`) layered on top of the already-built `LISTING_STATUS_TRANSITIONS`, so it stays honest as a seller-portal-specific view of the core table rather than a new core predicate.
- **`@fastify/multipart` is registered once in `sites/seller/index.ts`** (`attachFieldsToBody: true`), not per-route — every seller page could plausibly gain an upload, and Fastify's encapsulation already keeps it out of the other two sites.
- **The guarded routes sit inside a child plugin** (`sites/seller/index.ts` registers an inner `(guarded, ...) => { guarded.addHook('preHandler', requireSeller); ... }`) so `requireSeller` applies to every page except the sign-in routes, which guard their own `/account` individually and must stay reachable signed-out.
- **Dashboard now requires `requireSeller`.** It was a public placeholder from FEAT-001; real seller data can't be public. This changed two shared tests' expectations (see Deviations).
- **`formatDay`/`formatDate`/`formatDateTime` avoid `Intl`.** A hand-rolled month table is deterministic across environments and matches the prototype's other date formatting (`pageViewDay`-style plain string math) rather than depending on ICU data being present in the container image.
- **Multipart's `MultipartBody` type is hand-declared in `listing-form.ts`** rather than imported from `@fastify/multipart`'s `export =` + namespace-merged types, which are awkward to reference under `verbatimModuleSyntax`; the local type matches the confirmed runtime shape (`{ type: 'field', value } | { type: 'file', filename, mimetype, toBuffer() }`) exactly.

### Deviations from the discovery notes

- **No `payout-summary.ts`** in `app/core/reports/` — the seller portal never runs a payout (no button, per the outcome list), so nothing there needs to summarize a run. `status-label.ts`, `listing-status-tally.ts`, `activity-totals.ts`, `activity-timeline.ts` are the four ported.
- **No line touched in `app/app.ts`.** `sellerSite` was already registered by FEAT-001; the ticket's "one registration line" was already in place.
- **Two shared tests updated, both a direct, unavoidable consequence of `requireSeller` on `/seller`**: `app/test/smoke.test.ts` (signs in as seller before hitting `/seller`) and `app/plugins/site-render.test.ts`'s flash test (same, for its `/seller` request only — its `/admin` request and other assertions were left to FEAT-006, which was independently updating them at the same time). Both are outside `app/sites/seller/**`; committed only because leaving `/seller` calls unauthenticated in shared tests would leave the suite red for every ticket, not just this one.
- **Cross-seller/invalid-id coverage added beyond the ticket's explicit list**: a non-numeric `:id` on listings, orders, and notifications routes also answers 404 (`parseIdParam` returning null), exercised directly in each route's test file.

### Verified

- `docker compose run --rm app npm run typecheck` and `npm run lint` (scoped to `app/sites/seller app/core/reports`, and clean on the two touched shared test files): clean, no errors.
- `node --test 'app/sites/seller/**/*.test.ts' 'app/core/reports/**/*.test.ts' 'app/test/smoke.test.ts'`: **103 tests, 103 pass, 0 fail.**
- Whole-project `npm test` at hand-off: **967 tests, 965 pass, 2 fail** — both failures are `app/plugins/page-views.test.ts` (FEAT-006's page-view rollup, mid-flight in the same tree, unrelated to this ticket). `npm run coverage`: **99.47% lines, 96.01% branches, 98.76% functions**, both above the 90/80 gate.
- Curl walk against `http://localhost:4000` as a signed-in seller (magic link from the debug alert): dashboard, listings index/new/edit/show, orders, earnings, notifications all 200; created a listing with a real multipart image upload, confirmed the file served back at `/uploads/<uuid>.png`; moved it `draft → for_sale` (flash: `"…" is now for sale.`); attempted `draft → sold` on a second listing and got the flash refusal `A listing cannot move from draft to sold.`; another seller's listing id (`/seller/listings/1` and its `/status` POST) both answered 404.
- Money path proven end to end via `test-fixtures.ts` through the real actions (not fixture inserts): a delivered fulfillment shows `held $0.00` / `available $405.00` on both the dashboard and earnings page; a settled weekly payout run moves it to `paid out $405.00` and appears in the payout history table.
