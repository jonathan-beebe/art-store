---
id: IMPRV-006
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-006: Test gaps and fixture hygiene

## Problem
The same test fixture is written three to five times with disagreeing values. Shipping address: `app/test/commerce-world.ts:21` (`SHIPPING_ADDRESS`), `app/sites/shop/storefront-fixtures.ts:80` (`TEST_SHIPPING`, byte-identical), `app/sites/seller/test-fixtures.ts:26` (`DEFAULT_SHIPPING`, same person, different postcode/region/country), plus a fourth flat-form copy at `app/sites/shop/routes/checkout.test.ts:44-52`. Card numbers: `commerce-world.ts:14` declares `APPROVED_CARD = '4242 4242 4242 4242'` while `storefront-fixtures.ts:90` declares the same exported name `APPROVED_CARD = '4242424242424242'` — same name, different string — plus a third redeclaration in `checkout.test.ts:17`, a fourth inline literal in `seller/test-fixtures.ts:83`, a fifth in `db/seed-order-history.ts:16`. "Make a for-sale listing" and "cart holding a listing" and "place/pay for an order" each have two to three independent implementations across `commerce-world.ts`, `storefront-fixtures.ts`, and `seller/test-fixtures.ts`, and the `commerce-world` version inserts listing rows directly rather than walking the real lifecycle the other two use.

`app/db/migrator.test.ts:17,27,40` and `app/db/database.test.ts:19,30` clean up with a trailing `await db.destroy()` rather than `t.after`, and `database.test.ts:34,48` `mkdtemp` two temp directories that are never removed. Every other file in the suite (106 of them) uses `t.after` correctly.

`app/sites/shop/routes/fulfillments.test.ts:88,113,134,155` — four of five tests in the file assert only `assert.equal(response.statusCode, 404)`. `'confirming a delivery twice is refused the second time'` (line 91) asserts the second POST 404s but never asserts only one `released` ledger entry exists. `'another customer cannot confirm someone else's delivery'` (line 116) asserts no state: the fulfillment could have changed status and no test would catch it.

`app/sites/seller/routes/faqs.ts` is 97.74% lines / 64.00% branches. Lines 99-101, the `update` validation-error path, are uncovered, along with `parseFaqParams` returning null on a non-numeric `:faqId`, `findListingFaq` returning null on `unpublish`, `ownedListing` returning null on `publish`, and the `redirect_to` present/absent split on three of four handlers. `app/sites/admin/routes/customers.ts` is 66.67% branches (the invalid `?standing=` fallback and the anonymous-customer title fallback are untested); `app/sites/shop/routes/order-payments.ts` is 78.57% branches.

Named core edge cases are untested: `core/money.ts`'s `dollarsInputValue(-105)` returns `'1.05'` (the sign is dropped) while `formatCents(-105)` returns `'-$1.05'`, so `parseDollars(formatCents(x))` is not a round trip for negatives — untested. `core/escrow/payout-period.ts`'s five tests all use August 2026; the year boundary, a period spanning December/January, the leap day, and `asOf` at exactly midnight on a Monday are untested and unverified boundaries. `core/listings/listing-draft.ts` is 78.13% branches — `listingDraftErrors({})` and `parseListingDraft({})` are untested because the fixture always supplies all seven fields, and `priceError` rejects `$249`/`1,234.00` while `parseListingDraft` hands the same field to `parseDollars`, which accepts both, so the two grammars disagree. `core/listings/listing-slug.ts`'s `slugBase('Café au Lait')` drops the accent rather than transliterating it, untested.

`app/sites/shop/queries/*.ts` (7 files) and `app/sites/seller/queries/*.ts` (5 files) have no sidecar test — all report 100% line coverage through indirect route coverage, but result ordering, pagination boundaries, and null-on-outer-join behavior are untested directly. `app/sites/admin/queries/*` (15 files) all have sidecar tests, so this is an inconsistency between site query directories, not a universal gap.

## Goal
Fixtures share one definition per concern, and the identified untested branches and boundary cases are pinned with tests.

## Outcome
- One fixture module per concern with agreed values (shipping address, card numbers, listing/cart/order builders).
- The named core edge cases are pinned with literal-input tests (payout-period calendar boundaries, `listingDraftErrors({})`, money negatives, slug accents, email edge cases).
- Fulfillment and FAQ route tests assert observable behavior, not only a status code.
- Site queries that carry mapping logic have sidecar tests.

## Why it matters
Two exported constants with the same name (`APPROVED_CARD`) and different values is a trap for the next person who imports one expecting the other's behavior. A test asserting only a status code passes even when the invariant it exists to protect is violated — the delivery-confirmation double-submit test would pass today if a bug let the second confirmation write a second ledger entry, because nothing checks the ledger. Untested authorization and validation branches mean a guard nobody drives is a guard nobody knows works. The core edge cases named above (payout-period calendar boundaries, the two listing-draft price grammars disagreeing, the money sign-drop asymmetry) are the cases each module exists specifically to get right — the doctrine's "core gets exhaustive dependency-free unit tests" is not met by high line coverage alone when the untested lines are the boundary conditions.

## Discovery notes
- Promote one world-builder to `app/test/` and have the two site fixture files import from it, keeping only what is genuinely site-specific (e.g. `blockCustomer`, `removeListing`, `createTestNotification`). At minimum collapse `APPROVED_CARD`/`DECLINED_CARD`/`SHIPPING_ADDRESS` to one definition each — the spaced/unspaced card variance is accidental since the card-parsing helper strips separators anyway.
- `t.after(() => db.destroy())` and `t.after(() => rm(directory, { recursive: true, force: true }))` in `migrator.test.ts` and `database.test.ts`, matching the pattern in `listing-image-upload.test.ts:23-24`.
- Add the negative-state assertion after each 404 in `fulfillments.test.ts`: re-select the fulfillment and the ledger-entries rows and assert nothing moved.
- Four injections for the FAQ routes: `POST /seller/listings/abc/faqs`, `POST /seller/listings/:id/faqs/abc`, publish against another seller's listing, and an `update` with a blank answer. Same treatment for the admin customers `?standing=` fallback and the order-payments branches.
- The three edge-case groups worth doing now: payout-period calendar boundaries (year boundary, Dec/Jan span, leap day, midnight-Monday `asOf`), `listingDraftErrors({})`/`parseListingDraft({})`, and reconciling the two price grammars in `listing-draft.ts` (decide which one is correct and make the other match). The `?? []` branches noted on total `Record` lookups in `order-status.ts`/`fulfillment-status.ts` are a type-system artifact, not a test gap — a comment explaining that is more honest than chasing 100% coverage on them.
- `find-storefront-listings.ts` (search + medium filter + pagination) is the site query most worth a direct sidecar test; the admin queries directory already shows the pattern to follow.

Files expected to touch: `app/test/commerce-world.ts`, `app/sites/shop/storefront-fixtures.ts`, `app/sites/seller/test-fixtures.ts`, `app/sites/shop/routes/checkout.test.ts`, `app/db/seed-order-history.ts`, `app/db/migrator.test.ts`, `app/db/database.test.ts`, `app/sites/shop/routes/fulfillments.test.ts`, `app/sites/seller/routes/faqs.test.ts`, `app/sites/admin/routes/customers.test.ts`, `app/sites/shop/routes/order-payments.test.ts`, `app/core/money.test.ts`, `app/core/escrow/payout-period.test.ts`, `app/core/listings/listing-draft.test.ts`, `app/core/listings/listing-slug.test.ts`, `app/core/auth/email-address.test.ts`, new sidecar tests under `app/sites/shop/queries/` and `app/sites/seller/queries/`.

No hard ordering dependency against the other tickets in this manifest, though the fixture consolidation touches the same test files that RFCTR-003's form-parsing changes (`listing-draft.ts`) will touch — coordinate to avoid rework if both land close together.

## Related work
- 06-tests-views.md: "The same test fixture is written three to five times, with values that disagree", "`migrator.test.ts` and `database.test.ts` clean up with a trailing `await`, not `t.after`", "Four of five tests in `shop/routes/fulfillments.test.ts` assert only a status code", "Authorization and validation branches in the seller FAQ routes are untested", "Core edge cases: named missing tests", "Site queries have no sidecar tests", "1137 flat `test()` calls, no `describe`" (noted, no action required — the finding itself says nothing needs to change)
- 02-types-boundaries.md: "Two casts on `JSON.parse` results in tests"
- Related tickets: RFCTR-003 (touches `listing-draft.ts`'s parse/validate split, which overlaps the price-grammar reconciliation here)
