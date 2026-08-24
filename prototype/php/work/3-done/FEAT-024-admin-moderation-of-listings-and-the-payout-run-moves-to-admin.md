---
id: FEAT-024
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-024: Admin moderation of listings, and the payout run moves to admin

## Problem
Admins can block and lift a customer but cannot remove a listing from sale (temporarily for review or permanently) or lift a removal; the weekly payout is run by a seller-portal debug button that settles every seller from inside one seller's portal, with no admin payout page. `docs/alignment.md` §5 fixes both: `listing_removals` with `temporary | permanent` kinds and a lift, and payouts as a platform action from `/admin/payouts`.

## Goal
Platform actions — moderating listings and paying sellers — live on the admin site and nowhere else.

## Outcome
`POST /admin/listings/{listing}/removals` (kind, reason) takes a listing off the storefront whatever its status (browse, search, and `/art/{slug}` all stop showing it), the seller reads the reason on their own listing page and cannot put it back on sale, `…/removals/lift` works for temporary and is refused for permanent, at most one active removal per listing; `/admin/payouts?seller=` lists payouts and `POST /admin/payouts` (optional `as_of`) runs the same weekly payout the CLI runs, idempotent per period; the seller-portal payout button is gone and the seller's earnings page keeps balances and history; tests cover each refusal; `docs/admin.md` and `docs/escrow.md` updated.

## Why it matters
Retro item 6: payouts, refunds, and seller suspension are platform actions; a seller-portal button that pays every seller is a demo artefact the comparison should not carry.

## Discovery notes
Node's `docs/admin.md` "What a removal or a block actually does" diagram is the spec, including `isOnStorefront(status, hasActiveRemoval)` and `availableListingTransitions` dropping `for_sale` while a removal stands. The existing `BlockCustomer`/`LiftCustomerBlock` actions are the shape to mirror.

## Related work
- docs/alignment.md §5
- FEAT-010 (customer blocks)
- FEAT-003 (payout CLI)

## Working

Built in the order the ticket lays out, running `make test` after each step.

- **Migration + model + predicate**: `listing_removals` (prefix `rmv`),
  `ListingRemoval` model mirroring `CustomerBlock` exactly (`isActive()`,
  `lift(now)`), `ListingRemovalKind` enum (`temporary`/`permanent`,
  `canLift()`). `ListingAvailability` (already home to `isPurchasable`) grew
  `isOnStorefront(status, hasActiveRemoval)` and
  `availableTransitions(status, hasActiveRemoval)` — both pure, both covered
  by dataset sidecar tests. `Listing` grew the same relation/read shape
  `Customer` already carries for blocks: `removals()`, `activeRemoval()`
  (`HasOne`, `latestOfMany`), `currentRemoval()` (fresh read, matching
  `Customer::currentBlock()`'s documented reason), `hasActiveRemoval()`,
  `removalReason()`, plus `isOnStorefront()`/`availableTransitions()`
  wrapping the domain predicate.
- **Actions + refusals**: `RemoveListing`/`LiftListingRemoval` under
  `App\Actions\Listings`, structured exactly like
  `BlockCustomer`/`LiftCustomerBlock` — same `Story::for(...)->tell(...)`
  shape, same `DomainRuleViolation` refusals (already-removed, nothing
  active, permanent). `LiftListingRemoval` additionally refuses a
  `ListingRemovalKind::Permanent` removal, which `LiftCustomerBlock` has no
  analogue for.
- **Storefront + seller effects**: `Listing::forSale` scope (feeds browse and
  search) now excludes an active removal in addition to filtering on
  status — a removed listing keeps whatever status it held, `for_sale`
  included. `Shop\ListingController` and `AskSellerRequest` moved from
  `$listing->status->isOnStorefront()` to `$listing->isOnStorefront()`. The
  seller's own listing page (`seller/listings/show.blade.php`) shows the
  active removal's kind and reason; `seller/listings/index.blade.php` and
  `ChangeListingStatusRequest` both moved from `$listing->status->transitions()`
  to `$listing->availableTransitions()`, so the removal-aware set drives both
  the buttons and the 422 the status route answers on a stale or deliberate
  attempt to re-list.
- **Admin routes/pages + the `removed=` filter**: `POST
  /admin/listings/{listing}/removals` and `.../removals/lift`, mirroring the
  customer block routes. `RemovedFilter` enum (`any`/`removed`/`visible`);
  `Listing::ofRemoval` is a nullable-argument scope like `ofStatus`/`ofSeller`
  — `null` and the explicit `Any` case both add no clause, so an absent
  filter and `removed=any` read the same page. `admin/listings/index` gained
  the third filter select; `listings-table` gained a Removed column;
  `admin/listings/show` gained a moderation section (active removal + lift
  button when liftable, or the remove form) and a removal-history table.
- **Checkout wiring**: `Cart::placementPlan()` and `Order::placementPlan()`
  now pass `hasActiveRemoval: $item->listing->hasActiveRemoval()` instead of
  the hard-coded `false` IMPRV-004 left behind. Added action/model-level
  tests (`CartTest`, `OrderTest`) and an HTTP-level test
  (`CheckoutControllerTest`) for the `removed` reason blocking checkout.
- **Payouts move to admin**: `GET /admin/payouts?seller=` (`Admin\PayoutController`)
  and `POST /admin/payouts` (`Admin\RunPayoutController`, optional `as_of`)
  call the same `RunWeeklyPayout` action the CLI calls. `Payout::ofSeller`
  scope added for the filter. `Seller\PayoutController`, its route
  (`seller.earnings.payout`), its test file, and the "Run weekly payout now"
  button on `/seller/earnings` are deleted; the earnings page keeps balances
  and payout history only.
- **Logging**: `StoryEvent::ModerationRemoveListing` /
  `ModerationLiftListingRemoval` added; the enum's doc comment noting they
  were "waiting on a feature" is removed since the feature landed.
- **Docs**: `docs/admin.md` gained the removal/block mechanics section (with
  the Mermaid diagram modelled on Node's) and the payout-run section,
  replacing the "Not here yet" table this ticket closes out. `docs/escrow.md`
  gained the admin entry point in its "Code:" list, a paragraph naming both
  entry points as the only two, and the "Way in" table Node's doc carries.

### Deviations from §5, with reasoning

- §5 doesn't specify where the removed/visible/any filter's "empty means all"
  behavior lives. It's implemented as `Listing::ofRemoval(?RemovedFilter)`,
  matching `ofStatus`/`ofSeller`'s nullable-scope shape rather than
  `Customer::inStanding`'s non-nullable-enum-with-an-`All`-case shape — the
  ticket's own wording ("nullable-argument scope, empty meaning all") points
  at the former. The `RemovedFilter::Any` case still exists so the console's
  select can offer and mark it, and the scope treats `null` and `Any`
  identically.
- Not built: no dashboard tally for removed listings on `/admin`. The ticket's
  Outcome doesn't call for one and none of the three page-table rows it lists
  mention it; adding one would be scope beyond what was asked.
- Not built: no `admin_id` recorded on `listing_removals`, matching
  `customer_blocks`, which carries no admin id either — mirroring the shape
  to mirror rather than adding an audit column neither existing table has.

### Numbers

| Gate | Before | After |
| --- | --- | --- |
| Pest | 1699 tests, 4655 assertions | 1770 tests, 4796 assertions |
| Coverage | 100.0% lines | 100.0% lines |
| PHPStan (level max) | 0 errors | 0 errors |
| Pint | clean | clean |

`make check` green throughout — Pint, PHPStan, asset build, Pest at
`--min=100`.

### Review fix-up

A review of the removal machinery found three paths that bypassed it. All
three are closed.

- `/favorites` rendered every favorited listing whatever its status and
  whatever removal stood over it. `Customer::favoriteListings()` is a plain
  `belongsToMany` with no filter, and `FavoriteController::index` fed it
  straight into `listing-card.blade.php`. The page now asks
  `Listing::onStorefront()` — a new scope spelling `isOnStorefront`'s two
  halves as `where` clauses: `whereIn('status',
  ListingAvailability::storefrontStatuses())` and the shared `notRemoved`
  scope (`whereDoesntHave` an unlifted removal). One query, no per-row read.
  `forSale` and `ofRemoval`'s `Visible` branch were rewritten onto
  `notRemoved` so the removal predicate has one spelling.

  The favorite row stays. Node leaves it and hides the card, and this matches:
  the save outlives the removal, so lifting one puts the card back with
  nothing to re-favorite. A test asserts both — the card gone, `Favorite`
  still there — and another walks removal → lift → card back.

- `POST /cart/{slug}` never checked removal. `CartQuantity::withinStock` now
  takes `hasActiveRemoval` alongside the status and the stock, and refuses
  with the same `DomainRuleViolation` and the same sentence ("That listing is
  no longer for sale.") the sold-out and off-sale refusals already carry.
  `AddToCart` reads it off the listing. Checkout already refused a removed
  line; a stale slug no longer writes one into a cart in the first place.

- `RemoveListing` was a check-then-create with no transaction and no lock, so
  two concurrent removals could both see nothing active and both insert.
  `Listing::lockedForModeration()` / `takeForModeration()` follow
  `Fulfillment::lockedForTransition()` / `takeForTransition()`: the check now
  runs inside the transaction that writes, against the listing row held for
  update. The migration still carries no partial unique index (SQLite has
  none), so the action's rule remains the only thing holding a listing to one
  active removal — it now holds under contention.

  `BlockCustomer` had the identical window and is fixed the same way, with
  `Customer::lockedForModeration()` / `takeForModeration()`.

  The lock is asserted the way `FulfillmentTest` and `ListingTest` already
  assert it: the scope's query compiled against `MySqlGrammar` ends with `for
  update`. No fake concurrency test — SQLite serialises writers.

| Gate | Before | After |
| --- | --- | --- |
| Pest | 1775 tests, 4829 assertions | 1793 tests, 4865 assertions |
| Coverage | 100.0% lines | 100.0% lines |

Node and Rails carry the same three gaps if their favorites page, cart add,
and moderation actions were built from the same shapes; Node's favorites
query and cart route are already the reference here, so only the two
moderation actions are open questions there.
