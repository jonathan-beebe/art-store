---
id: IMPRV-001
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-001: Behavioral test gaps, factories for every model, and seeders through actions

## Problem
Behaviors with no test: the debug payout button pays every seller (`Seller/PayoutController.php:14-16`, documented in `docs/review.md` gap #5) while `PayoutControllerTest.php:43` asserts only the signed-in seller's payout; a customer magic link carrying `redirect_to=/seller/...` is "local" and bounces a customer into the seller portal (`Auth/MagicLinkVerificationController.php:56-60`); merging the same anonymous customer twice writes a second `customer_merges` row and a stale cookie resolving through a chain of merges is unasserted (`MergeAnonymousCustomer.php:31-34`, `ResolveCustomerFromCookie.php:22`); `PayoutPeriod` has no year-boundary case; listing quantity `0` and oversized uploads are not rejected by a test (`ListingControllerTest.php:128-134` covers `-1` only); `card_number` `max:32` is untested; a search of only wildcards and a medium filter matching nothing are untested. Factories exist for 3 of 17 models (`Customer`, `Listing`, `Seller`); `ListingFactory` has no `archived()` state, so the archived branch of `ListingAvailability` has no fixture; nine tests hand-build rows with `Model::create([...])`. `database/seeders/ListingSeeder.php:24-34` writes listings directly with its own `Str::slug` (a second slug implementation without `ListingSlug`'s collision handling) and seeds `Sold` listings that never passed through `for_sale`; `CustomerSeeder.php:68` writes a `Favorite` and hand-records its event where `ToggleFavorite` pairs both. `docs/review.md` gap #1 lists four classes with no sidecar (`SignInSeller`, `SignInCustomer`, `ClaimCustomerIdentity`, `ResolveCustomerFromCookie`).

## Goal
Every documented behavior and every known gap has a test that pins it, and fixtures come from factories or the real actions.

## Outcome
- Tests exist for each behavior listed above (Pest `it()` names matching the behavior), including a test that pins the payout button's all-sellers behavior and tests for the four classes without sidecars.
- A factory with meaningful states exists for every model; tests use factories instead of `Model::create([...])` for simple rows and the action walk for lifecycle states.
- `ListingSeeder` and `CustomerSeeder` go through `CreateListing`/status changes and `ToggleFavorite`; `make fresh` produces the same demo (same listing titles, statuses, and order history), and the seeder tests pass.
- Coverage is at or above 98% lines with 100% on `app/Domain`.

## Why it matters
The review doc's honesty about gaps is good; closing them is better, and factories are what a Laravel reviewer reaches for first when reading tests.

## Discovery notes
- Keep action-built lifecycle fixtures as the default for orders; factories cover rows that are plain data.
- For the customer-link-to-seller-portal case, a decision is needed: reject the destination (fall back to the default) or accept it; the test should pin whichever `docs/identity.md` says.

## Related work
- RFCTR-001
- RFCTR-005
