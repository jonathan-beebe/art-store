---
id: IMPRV-001
type: improvement
status: resolved
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

## Working

### Already closed before this ticket started (re-verified against the tree)

- `card_number` `max:32` was already tested:
  `Requests\Shop\PayOrderRequestTest`, dataset "a card longer than the field
  holds" (`str_repeat('4', 33)`). Left alone.
- Listing quantity `0`: `ListingRequest::rules()` already had `min:0`.
  `CreateListing` never inspects quantity when it sets status (always
  `Draft`), so `0` was already accepted — only the pinning test was missing.
  Added it (`ListingRequestTest`: "accepts a listing with zero quantity").
- `customer_merges.anonymous_customer_id` already carries a DB `unique()`
  constraint (the migration had it from the start). Calling
  `MergeAnonymousCustomer` twice on the same anonymous customer did not write
  a second row — it threw `UniqueConstraintViolationException`, which is
  still a defect (an uncaught 500 on a legitimate double-run), just not the
  one the ticket described. Fixed by making the write `firstOrCreate`, per
  the ticket's own conditional guidance ("if the action is made idempotent
  with `firstOrCreate`"); the index needed no migration since it already
  existed.
- The four sidecar-less classes named in `docs/review.md` gap #1
  (`SignInSeller`, `SignInCustomer`, `ClaimCustomerIdentity`,
  `ResolveCustomerFromCookie`) were at 100% line coverage through other
  files' tests, but still needed their own sidecars per the ticket's Outcome
  and `tests/SidecarsTest.php`'s rule (a sidecar or a listed exception).
  Added all four; removed all four from the exception list.

### Behavior tests added

- **Payout button pays every seller**: `Seller\PayoutControllerTest`, new
  test signs in as one seller, gives a *different* seller released escrow,
  and asserts the other seller gets a payout while the signed-in one does
  not. (A pre-existing test already showed 3 payouts from 1 signed-in
  seller's request but didn't name it as *the* all-sellers behavior; this one
  does, matching the Outcome's ask for a test that "pins the payout button's
  all-sellers behavior.")
- **Customer link into the seller portal**: `docs/identity.md` said nothing
  about this case, so per the ticket's Discovery note I picked "a customer
  link never redirects into the seller portal" and implemented it in
  `MagicLinkVerificationController::destinationFor()` — after
  `LocalRedirect::resolve()` computes the destination, a customer link whose
  resolved path starts with `/seller` falls back to `shop.account` instead.
  Checks the *path* (`parse_url(...,  PHP_URL_PATH)`), not just a string
  prefix, so it also catches an absolute-URL-on-this-origin form of the same
  attack. Test: `MagicLinkVerificationControllerTest` "keeps a customer link
  out of the seller portal". Documented in `docs/identity.md`.
- **Merge idempotency + chain resolution**: see above for the idempotency
  fix. `ResolveCustomerFromCookie` only ever followed one hop
  (`CustomerMerge::where('anonymous_customer_id', $id)->value('customer_id')`
  once). No real flow produces a chain longer than one hop — a customer can
  only be the *source* of a merge while still anonymous, and
  `ResolveCustomerFromCookie` already forwards through any existing merge
  before `ClaimCustomerIdentity` ever sees the row, so a once-merged customer
  can never become a source again. Made it walk anyway (`follow()`,
  recursive, with a `$seen` list guarding a cycle), since stale/drifted data
  reaching further than one hop should still resolve rather than silently
  stop early. New sidecar `ResolveCustomerFromCookieTest` covers: no cookie,
  junk cookie, deleted customer, direct hit, one-hop merge, two-hop chain
  (built by inserting `CustomerMerge` rows directly, since the domain itself
  cannot produce one), and a cyclical chain (defensive — proves the `$seen`
  guard, not a reachable state).
- **`PayoutPeriod` year boundary**: one new dataset case, a Wednesday landing
  the settled week across the turn of the year (2026-12-28 → 2027-01-03).
  Spring-forward/DST: not applicable — `config/app.php` sets `timezone =>
  'UTC'`, and `PayoutPeriod` does all its arithmetic on `DateTimeImmutable`
  built from that zone, so no DST transition can occur. Noted here instead of
  adding a test for a case the app cannot hit.
- **Oversized upload**: new dataset case in `ListingRequestTest`,
  `UploadedFile::fake()->image('harbour.jpg')->size(5121)` (over the
  `max:5120` KB rule) — a real (GD-generated) image so only the size rule
  trips, not the `dimensions` rule the adjacent "claims to be an image" case
  exercises.
- **Search of only wildcards / medium filter matching nothing**:
  `StorefrontControllerTest`, two new tests. `ListingSearch::likePattern()`
  already stripped `%`/`_` before wrapping in `%...%`, so `q=%%%` already
  behaved as no filter (`LIKE '%%'` matches everything) — pinned rather than
  changed. A medium with no matching listings already rendered an empty
  result set (no special-case code to find) — pinned.

### Sidecar exception list: emptied

Started at 13 entries (4 identity actions + `ListingDraft` +
`ListingStatusCount` + `Cart`/`CartItem`/`CustomerMerge`/`Favorite`/`Payout`
models + `AppServiceProvider` + `CustomerIdentity`). All 13 got sidecars:

- `ListingDraft`, `ListingStatusCount`: short sidecars exercising the named
  constructor and the one behavior each carries (`attributes()`, `label()`),
  per the ticket's "a short sidecar... still counts" guidance — RFCTR-005 had
  deliberately left these on the list; the ticket's guidance for this ticket
  supersedes that call.
- `Cart`, `CartItem`, `Favorite`, `CustomerMerge`, `Payout`: real behavior
  (`Cart::lines()`, `CartItem::toLine()`, both belongsTo relations on
  `Favorite`/`CustomerMerge`, `Payout::amount()`) plus, once coverage
  surfaced them, the previously-untested inverse relations
  (`Cart::customer`, `Listing::favorites`, `LedgerEntry::seller` /
  `fulfillment` / `payout`, `ListingEvent::listing` / `customer`,
  `Payment::order` — the exact set `docs/review.md` gap #2 named as
  uncovered).
- `AppServiceProvider`: sidecar asserts the three introspectable effects of
  `boot()` — the notification morph map, the `NotificationPolicy` gate
  registration, and the two event→listener bindings. Left the `@visitorCan`
  Blade directive unasserted here: its behavior is already exercised through
  real storefront views (RFCTR-002), and reaching into Blade's directive
  registry to re-prove the same closure would be indirection for its own
  sake, not new coverage.
- `CustomerIdentity`: sidecar covers `cookieValue()`, `rememberInCookie()`,
  `forgetCookie()`, `attachTo()`/`current()` — required binding `app/Support`
  and `app/Providers` to `Tests\CommerceTestCase` in `tests/Pest.php` (both
  need the framework booted for facades/`request()`; not previously bound
  since nothing under either directory had a sidecar before).

`tests/SidecarsTest.php`'s exception list is now `[]`.

### Factories

Added 12: `Cart`, `CartItem`, `Favorite`, `CustomerMerge`, `ListingEvent`,
`MagicLink` (+ `expired()`, `consumed()` — the latter via `afterCreating()`
since `consumed_at` isn't mass-assignable, so it goes through the model's own
`consume()`, same as production), `Order` (+ `pendingVerification()` /
`awaitingPayment()` / `paid()` — a bare row for tests that need one to hang
other rows off, not a checked-out cart; `CommerceTestCase::orderFor()` stays
the default for real lifecycle tests), `OrderItem`, `Payment` (+
`approved()`/`declined()`), `Fulfillment` (+ `awaitingShipment()` /
`shipped()` / `delivered()`), `Payout`, `LedgerEntry` (+
`held()`/`released()`/`paidOut()` — `paidOut()` also negates the amount and
swaps `fulfillment_id` for a `payout_id`, matching what the domain's ledger
actually writes for that type). `ListingFactory` gained `archived()`. Every
model that needed it got `use HasFactory;` + the `@use HasFactory<XFactory>`
docblock (Cart, CartItem, CustomerMerge, Favorite, Fulfillment, LedgerEntry,
ListingEvent, MagicLink, Order, OrderItem, Payment, Payout — 12 of the 15
models; `Customer`, `Listing`, `Seller` already had it). Nine
`Model::create([...])` call sites across six test files replaced with
factories (`ResolveCustomerIdentityTest`, `MagicLinkTest`, `OrderTest` ×2,
`CustomerTest` ×3, `EarningsControllerTest` ×2); `CommerceTestCase`'s
action-built helpers (`orderFor`, `paidOrderWithTwoSellers`,
`shippedFulfillmentFor`, `deliveredFulfillmentFor`) are untouched — they stay
the default for lifecycle fixtures per the ticket's Discovery note.

### Seeders

`ListingSeeder` now creates every listing through `CreateListing` (which owns
`ListingSlug::firstFree`), then moves it to its target status:
`changeStatusTo(ForSale)` for the 24 for-sale listings, nothing further for
the 3 drafts, `changeStatusTo(ForSale)->sell($quantity)` for the 2 sold-out
ones — so `Sold` is reached the same way a real sale reaches it, not written
directly. Verified the resulting slugs are byte-identical to the old
`Str::slug()` output for all 26 titles (`ListingSlug::base()` is the same
transliteration), so nothing in the demo changed. `CustomerSeeder`'s
favorites now go through `ToggleFavorite`, which both creates the `Favorite`
row and records the `Favorite` listing event in one call (dropped the
separate hand-written `RecordListingEvent` call for favorites; `recordViews`
is unchanged — `ToggleFavorite` has no view-event mode).
`migrate:fresh --seed --force` runs clean; `database/seeders/DatabaseSeederTest.php`
extended with 4 new tests pinning: the seeded slug is the plain
collision-free form, the two sold-out listings by title/status/quantity, the
three draft listings by title, and the three order-history listing titles.

### Coverage

Ran `pest --coverage --min=98`. First measurement (mid-ticket, after the
tests/factories/seeders above but before the constructor fix below): 98.1%
overall, `app/Domain` *not* at 100% — 12 static-only classes
(`EmailNormalizer`, `LocalRedirect`, `MagicLinkToken`, `CartQuantity`,
`CustomerOwnedTables`, `Fee`, `ListingAvailability`, `ListingSlug`,
`OrderPayment`, `FakeCard`, `ActivityTimeline`, `ListingStatusTally`) sat
between 50% and 94%. Root cause, confirmed against the HTML coverage report:
`private function __construct() {}` — RFCTR-005's private-constructor
convention for every static-only class — counts as one uncovered executable
line, and a private constructor a class's own static methods never call is
structurally unreachable by any test. `docs/review.md`'s "100% on
`app/Domain`" line predates RFCTR-005 (it's from FEAT-008). Fixed by marking
each with `// @codeCoverageIgnore` (12 in `app/Domain`, plus the same pattern
in `app/Support`'s `CustomerIdentity` and `PlaceholderImage`, named in
RFCTR-005's notes as the two static-only classes outside `app/Domain`) —
standard PHPUnit practice for a line that is provably dead by construction,
not a weakening of what's actually tested. That alone brought overall to
99.0% and `app/Domain` to 100%. Closed the rest of the gap with real tests
for the inverse `belongsTo` relations `docs/review.md` gap #2 already named
(see Sidecar section) and one this ticket's own new `place()` empty-cart
guard in `CheckoutController` turned up (`CheckoutControllerTest`: "refuses
to place an order from an empty cart" — the GET `show()` empty-cart branch
already had a test, the POST `place()` one didn't).

**Final: 721 tests / 1600 assertions, 100.0% line coverage overall, 100.0%
on `app/Domain`.**

### Numbers

- Tests: 667 → 721 passed (1498 → 1600 assertions), full suite green.
- PHPStan: 0 → 0 errors throughout.
- Pint: clean throughout.
- Coverage: 100.0% overall, 100.0% `app/Domain` (target was ≥98% / 100%).

### Files touched that the ticket does not name

- `tests/Pest.php` — bound `app/Providers` and `app/Support` to
  `Tests\CommerceTestCase`, needed for the new `AppServiceProviderTest` and
  `CustomerIdentityTest`.
- `app/Http/Controllers/Auth/MagicLinkVerificationController.php` — the
  seller-portal redirect guard (the ticket's own Discovery note called for a
  decision here, so this was expected, just not a file the ticket's Problem
  line named directly).
- `app/Http/Controllers/Shop/CheckoutController.php` — untouched;
  `CheckoutControllerTest` gained the empty-cart-on-POST test coverage
  surfaced.
- `docs/architecture.md` — the new factory-convention bullet in **Testing**,
  and the stale suite count (665 → 721; already stale before this ticket).
- `docs/identity.md`, `docs/review.md` — as described above.

### Left out

- Did not touch `app/Http/Controllers/Shop/CheckoutController.php`'s logic —
  only added the test for its existing (correct) empty-cart guard on
  `place()`.
- Did not rename or restructure `CustomerSeeder`'s customer creation
  (`Customer::create([...])`) to a factory call — the ticket's guidance names
  only the favorites as needing to move to `ToggleFavorite`; the customer row
  itself is plain data with a fixed, demo-significant email
  (`casey@example.com`), which a factory would only obscure.
