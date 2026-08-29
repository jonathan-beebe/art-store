---
id: BUG-014
type: bug
status: resolved
created: 2026-08-28
---

# BUG-014: Removing a listing's options or variants still dead-ends

## Problem
Reported live on 2026-08-28, after BUG-008 (commit dce13e5) shipped the
variant destroy route, row control, and `forVariant` guard: tapping Remove on
an option value answers "This option value is selected by a variant; remove
that variant first." and tapping Remove on the axis answers "This axis has a
variant built from one of its values; remove or reassign that variant first."
The reporter states they cannot remove the listing's options or variants —
the variant-removal path BUG-008 added is not getting them to "removed."

## Goal
A seller can actually remove an option value, an axis, and the variants in
the way, end to end, through the live UI.

## Outcome
On a real listing in the running app, the seller removes the blocking
variant(s) and then the option value and axis, with each control they need
visible and working. If the block is a live-data reference or a message not
pointing at the working control, that specific defect is fixed and covered by
a test.

## Why it matters
BUG-008's fix has an end-to-end feature test proving the path, yet the
reporter hit the same dead end in the live app the next day — either the
shipped control fails or is invisible in practice, or a reference the tests
don't seed (cart or order rows) re-blocks the path, or the guard messages
send the seller to a screen where the needed control cannot be found.

## Discovery notes
Root-cause candidates to check, in order:
1. `ConfiguratorDeletionGuard::forVariant` refuses when ANY `CartItem` or
   `OrderItem` references the variant. Seeded/demo listings and any listing
   with sales history hold order rows forever — that would make every such
   variant permanently undeletable, re-creating the BUG-008 dead end for any
   listing that has ever sold. Decide what the guard should actually protect
   (live carts and open orders vs. all history — order items freeze their
   own snapshot in `price_breakdown_json`, so a historical reference may not
   need the live row).
2. The variant Remove control shipped on the Combinations & stock screen,
   but the guard messages on the Choices screen say "remove that variant
   first" without naming where — verify the control renders and works on the
   reporter's listing shape, and whether the message should point at the
   screen.
3. Reproduce in the running app (`make up`, port 8000), not only in tests.

## Related work
- BUG-008 (prototype/php/work/3-done/) — the destroy path this report says
  still dead-ends

## Working

### Reproduction
Seeded/demo data holds no cart or order rows referencing any variant
(`CartItem::whereNotNull('variant_id')->count()` and the `OrderItem`
equivalent are both 0 against the seeded database) — the seeders never add a
configured line to a cart or order, so the dead end the reporter hit is not
in the shipped demo data itself; it reproduces from the reporter's own
in-app testing (add a configured variant to a cart or place an order against
one, then try to remove it).

Reproduced directly against a listing with axes (`Engraved Signet Ring`,
`lst_01M144AAV687VGD5CX02APW7BK`) via `php artisan tinker` inside the running
container: placed, paid, shipped, and delivered an order against one of its
variants, then called `DeleteVariant`. Confirmed refused even after
`delivered`:

    DELETE BLOCKED: This combination is in a cart or an order; turn off "Offered" instead of removing it.

/ then confirmed removing the option value or axis behind that variant
refuses too, with the guard message but no way forward — the variant
control on Combinations & stock also refuses, so there is no path to
"removed" for a variant with any sales history, however old.

### Root cause
`forVariant` (`ConfiguratorDeletionGuard`) refuses on the mere *existence* of
any `CartItem` or `OrderItem` row naming the variant, with no regard for
whether the order (or cart) can still act on that reference. Every variant
that has ever sold is undeletable forever, which re-creates the BUG-008 dead
end for any listing with sales history — seeded or not.

This is worse than a discoverability gap: it can crash a still-open order.
`order_items.variant_id` is `nullOnDelete`, and two live code paths read
`OrderItem::variant` (via `StockMovement::variant()`) and throw a
`LogicException` if it is `null`:
- `CancelOrder::restockItems` — reachable while an order is
  `pending_verification` / `awaiting_payment` (`releasesStockOnCancel()`).
- `FinalizeOrder::sellItems` — reachable on a retry from `payment_failed`
  (`retakesStockOnRetry()`), which re-claims stock via `StockMovement::claim`.
- `DeclineFulfillment::restockItems` — reachable while a fulfillment is
  `awaiting_shipment`.

All three only ever fire while the order's `Fulfillment` row (matched by
`order_id` + `seller_id`, per `docs/alignment.md` §4.1) is still
`awaiting_shipment` — `PlaceOrder` creates that row (default status
`awaiting_shipment`) at placement time, before payment, so it already covers
the pending/awaiting/failed-payment states without a separate order-status
check. Once a fulfillment reaches `shipped`, `delivered`, `declined`, or
`refunded`, none of the three paths touch that order item's variant again:
decline only transitions from `awaiting_shipment`
(`FulfillmentStatus::transitions`), and `RefundFulfillment` "does not restore
stock" (`docs/alignment.md` §4.1) — it never reads `variant`.

### Decision: protection scope
`forVariant` should refuse only for a live `CartItem`, or an `OrderItem`
whose order still has an `awaiting_shipment` fulfillment for that item's
seller. An `OrderItem` behind a `shipped`/`delivered`/`declined`/`refunded`
fulfillment is safe to delete the variant under: `title`,
`unit_price_cents`, and `price_breakdown_json` are frozen at placement
(`docs/item-configurator.md` snapshot doctrine, mirrored in the
`order_items` migration's own comment), so the order still renders
correctly with `variant_id` nulled, and no code path reads that item's
`variant` again.

Implemented as `OrderItem::awaitingShipment()`, a query scope doing the
`fulfillments` lookup described above, used from `DeleteVariant` alongside
the unchanged live-cart check.

### Discoverability
Confirmed the Remove control BUG-008 added is live and reachable on
Combinations & stock (`variants/index.blade.php`, wired to
`VariantController::destroy`) — the control itself is not the defect. The
Choices-screen guard messages (`forAxis`/`forOptionValue`) said "remove that
variant first" without naming where; sharpened both to name "Combinations &
stock".

### Files changed
- `src/app/Domain/Configurator/ConfiguratorDeletionGuard.php` — `forAxis`/
  `forOptionValue` messages name the Combinations & stock screen.
- `src/app/Models/OrderItem.php` — added the `awaitingShipment` query scope.
- `src/app/Actions/Configurator/DeleteVariant.php` — `forVariant`'s order
  check narrows to `awaitingShipment()`.
- Tests: `ConfiguratorDeletionGuardTest`, `DeleteVariantTest`,
  `VariantControllerTest` — updated/added cases below.

### Tests
- `ConfiguratorDeletionGuardTest`: `refuses to delete an axis a variant
  references, naming where to remove it`, `refuses to delete an option value
  a variant references, naming where to remove it` (exact message text).
- `DeleteVariantTest`: `refuses to delete a variant an order still awaiting
  shipment holds`, `deletes a variant only a shipped order references`,
  `deletes a variant only a delivered order references` (existing
  cart/no-reference cases unchanged).
- `VariantControllerTest`: `refuses to remove a variant an order still
  awaiting shipment holds`, `removes a variant only a delivered order
  references` (end-to-end through the destroy route).

Full suite: 2732 passed (7794 assertions); coverage gate 100%.
