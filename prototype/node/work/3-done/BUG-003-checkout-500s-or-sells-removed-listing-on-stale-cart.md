---
id: BUG-003
type: bug
status: resolved
created: 2026-08-23
---

# BUG-003: Checkout can 500 or sell a removed listing when the cart is stale

## Problem
`POST /cart/:slug` is the only place that asks whether a listing may be
bought — `if (!found.isPurchasable) { … }` (`sites/shop/routes/carts.ts:54-57`).
`POST /checkout` (`sites/shop/routes/checkout.ts:82-113`) checks only
`contents.lines.length === 0`. `placeOrder`
(`actions/orders/place-order.ts:112-127`) then calls `takeStock`, which calls
`stockAfterSale` (`core/listings/listing-stock.ts:15-20`), which throws
`throw new RangeError(\`a listing that is ${status} cannot be sold\`)` when
the listing is no longer `for_sale`, and
`\`a listing with ${quantity} left cannot sell ${sold}\`` when stock ran
short. `RangeError` is not `TransitionError`, so no route catches it. Two
reachable cases: another buyer takes the last unit while the cart sits (500 on
checkout), and an admin removes the listing while the cart sits (the removal
is invisible to `stockAfterSale`, so a removed piece is sold).

Two related transaction gaps let the same staleness through at other points:

`app/sites/shop/routes/checkout.ts:113,116` — `placeOrder(...)` commits in its
own transaction (order, items, fulfillments, stock claimed, cart emptied),
then `finalizeOrder(...)` opens a second transaction to charge the card, move
stock again, and hold escrow. A crash or a concurrent cancel between the two
leaves an order placed with stock claimed and no payment row.

`app/sites/shop/routes/carts.ts:51,54,62` — `findListingOnStorefront(db, slug)`
reads the listing and its active removal (51); the handler branches on
`found.isPurchasable` (54); `addToCart` then opens its own transaction (62)
and re-reads only `listings.quantity` — never the status or the removal. A
listing that is removed or taken off sale between 51 and 62 still lands in
the cart.

## Goal
A stale cart cannot 500 checkout and cannot result in a sold listing that is no longer purchasable.

## Outcome
- Placing an order whose lines are no longer purchasable (sold out, off sale, removed) answers the customer with which lines are unavailable.
- A refused placement leaves no order behind.
- Placement and the card charge commit or roll back as one unit.
- Add-to-cart's purchasability gate and its insert see one snapshot of the listing.

## Why it matters
"Core returns explicit results rather than throwing for expected business
cases" — a stale cart at checkout is an expected business case, not a
programmer error, and today it reaches the customer as a 500. "A branch on a
business rule inside a route handler is a smell" — the purchasability rule
lives at one entry point (add-to-cart) instead of at every write that depends
on it. "Any read-then-write runs inside a single transaction" — placement and
charging are two transactions today, and the cart's purchasability read and
its insert are two more.

## Discovery notes
Give core a function that takes the priced cart lines plus each listing's
current status/stock/removal and returns a discriminated result (`placeable`
with the lines, or `unavailable` with the offending lines). `placeOrder` calls
it and returns the refusal; `cartContents` already selects `status` and
`availableQuantity`, so the shell has the data. The cart page can then show
the same answer instead of the storefront being the only gate.

`runInTransaction` already supports joining, so wrapping the `placeOrder` /
`finalizeOrder` call in one `runInTransaction(context, …)` and passing the
transacted context to both actions makes them one unit with no change to
either action's body.

For the cart insert, either wrap the handler body in one transaction and pass
the transacted context to `findListingOnStorefront` and `addToCart`, or move
the purchasability read inside `addToCart` so the gate and the insert see one
snapshot.

Files expected to touch: `app/sites/shop/routes/checkout.ts`,
`app/sites/shop/routes/carts.ts`, `app/actions/orders/place-order.ts`,
`app/core/listings/listing-stock.ts`, `app/core/cart/cart-quantity.ts`
(the `RangeError` half of core-throws-for-business-cases applies to both).

No dependency on BUG-002. Independent of BUG-004; touches some of the same
routes/actions as BUG-005 (both are about read-then-write transaction
boundaries) but the affected call paths do not overlap — no ordering
requirement between them.

## Related work
- 03-core-shell.md — "Cart contents are never re-checked at placement; core throws `RangeError` into a 500"
- 03-core-shell.md — "Core throws for expected business cases" (the `RangeError` half: `stockAfterSale` / `quantityWithinStock`)
- 04-data-layer.md — "Checkout places and charges an order in two separate transactions"
- 04-data-layer.md — "Purchasability is checked outside the transaction that writes the cart item"
- BUG-005 (adjacent transaction-boundary tickets; no ordering dependency)

## Working

Verified against the code first. All three claims still held:
`POST /checkout` checked only `contents.lines.length === 0`; `takeStock` ran
`stockAfterSale`, whose `RangeError` no route catches; an active removal was
invisible to placement, so a removed piece sold. `placeOrder` and
`finalizeOrder` each opened their own transaction, and `POST /cart/:slug` read
purchasability outside the transaction that wrote the line.

Changed:
- `app/core/orders/order-placement.ts` (new, + test): `planOrderPlacement`
  takes the cart lines with each listing's status, available quantity, and
  whether a removal stands over it, and returns `{ ok: true, lines }` or
  `{ ok: false, unavailable }` with one reason per line
  (`removed` | `off_sale` | `sold_out` | `short_stock`). `unavailableNotices`
  turns those into the finished `{ title, notice }` view model. Generic in the
  line so `placeOrder` keeps the priced view it read.
- `app/actions/orders/place-order.ts`: reads the cart and each listing's active
  removal inside its transaction, calls the plan, and returns
  `PlacedOrder` — the order, or the refusal — before anything is written.
  `placeOrderOrThrow` is the unwrapping wrapper for seeds and fixtures, which
  build the listings they buy.
- `app/core/listings/listing-stock.ts`, `app/core/cart/cart-quantity.ts`: still
  throw, each with a comment naming what settles the expected cases first
  (`planOrderPlacement`, `isPurchasable`).
- `app/sites/shop/routes/checkout.ts`: `checkOutCart` wraps `placeOrder` +
  `finalizeOrder` in one `runInTransaction`; a refusal re-renders checkout at
  422 naming each unavailable piece, matching the incomplete-form idiom on the
  same route, and leaves the order, the payment, and the cart untouched.
- `app/sites/shop/views/checkout.ejs`: alert block over the finished
  `unavailable` view model (loop and interpolation only).
- `app/sites/shop/routes/carts.ts`: `findListingOnStorefront` + `currentCart` +
  `addToCart` run in one `runInTransaction`; the handler branches on the
  outcome the transaction returns.
- Call sites moved to `placeOrderOrThrow`: `app/test/commerce-world.ts`,
  `app/sites/seller/test-fixtures.ts`, `app/sites/shop/storefront-fixtures.ts`,
  `app/db/seed-order-history.ts` (one-word change each).

Left alone: `cartContents` does not carry the removal flag — the refusal
carries the unavailable lines, so the cart page needs no second source. The
`RangeError` from `checkoutTotals` on an empty cart stays: both routes refuse
an empty cart before placement, so reaching it is a programmer error.

What I could not provoke: a failure inside `finalizeOrder` after the charge
that a request can actually reach — a decline is a legitimate outcome, and
nothing else in that path fails for reachable data. The rollback test asserts
the structural claim instead: `placeOrder` + `finalizeOrder` under one
`runInTransaction` that then throws leaves no order, no payment, and the cart
intact. Route-level cover is the pair of observable ends — a paid checkout
leaves exactly one order and one payment; a refused one leaves neither.

Tests: 1214 before, 1273 after with the whole suite green (21 of the new tests
are this ticket's: 11 core, 3 on `placeOrder`, 5 on `POST /checkout`, 2 on
`POST /cart/:slug`; the rest arrived from tickets running beside this one).
Confirmed the three stale-cart route tests fail without the plan.
