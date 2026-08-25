---
id: FEAT-003
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-003: Commerce domain core — listings, cart, orders, payments, fulfillment, escrow, payouts, notifications, moderation predicates

## Problem
Nothing in `prototype/node/src/app/core` or `app/actions` models the product: no listing lifecycle, no cart, no order state machine, no fake card, no per-seller fulfillment, no escrow ledger, no payouts, no notifications, and no predicates for admin moderation. The portal (FEAT-004), storefront (FEAT-005), and admin site (FEAT-006) each need the same rules and must not each invent them.

## Goal
One tested functional core and one set of actions that every site calls, so a listing can be created, sold, paid for, shipped, delivered, and paid out without any HTTP in the loop.

## Outcome
- Pure core modules with sidecar tests for: listing status transitions and stock; listing availability (`for_sale`, no active removal, slug page reachable through `sold`); listing draft validation; cart lines and totals; order status transitions including `cancelled`; fulfillment status transitions; order roll-up from fulfillments; fake card decision; platform fee; ledger movement and balance fold; payout period (Monday–Sunday, most recently completed week); notification messages; customer standing (blocked or not).
- Migrations and Kysely row types for `listings`, `listing_events`, `favorites`, `carts`, `cart_items`, `orders`, `order_items`, `payments`, `fulfillments`, `ledger_entries`, `payouts`, `notifications`, `listing_removals`, `customer_blocks`, `page_view_counts`.
- Actions, integration-tested against `:memory:`: create / update listing, change listing status, record listing event (view deduped per listing+customer+hour), add to / remove from cart, current cart, place order (stock taken, fee and net stored per fulfillment), mark awaiting payment, finalize order (one `payments` row per attempt, declined restores stock, approved holds escrow and notifies sellers), cancel order (restores stock), mark shipped (notifies customer, rolls up), confirm delivered (releases escrow, rolls up), run weekly payout, notify, mark notification read.
- `npm run payouts -- --as-of=2026-08-24` creates payouts rows for the completed week and re-running is a no-op.
- An end-to-end lifecycle test drives listing → cart → place → finalize → ship → deliver → payout with only actions and asserts the ledger and balances at each step; a declined-then-retry test and a cancel test sit beside it.

## Why it matters
Every user-facing ticket is a thin shell over this core; getting it right once keeps the three sites consistent and keeps domain `if`s out of routes.

## Discovery notes
Port `prototype/rails/src/app/domain/**` (with its sidecar tests — they are the spec) and `app/actions/**` to TypeScript. `docs/orders.md`, `docs/escrow.md`, `docs/ontology.md` in the Rails docs explain the intent. Keep the Rails decisions listed in `docs/architecture.md` here: fee at placement stored on the fulfillment, verify-before-card, `cancelled` reachable, per-(order, seller) fulfillments.
- FEAT-002 runs in parallel and owns `sellers`, `customers`, `admins`, `magic_links`, `customer_merges`. Reference them by id; do not create them. Edit only your own lines of `app/db/schema.ts`. Migrations are timestamped files, so no collision.
- Enumerations as `as const` unions (`erasableSyntaxOnly` forbids `enum`); transitions as a `Record<Status, readonly Status[]>` with `canTransition`.
- Actions take `{ db, clock }` (and a `notificationDelivery` where they notify), run in `db.transaction().execute(...)`, and throw a typed `TransitionError` on an illegal move so routes can render a refusal.
- Stock: a purchase decrements at placement; a decline or cancel restores; `sold → for_sale` is legal for that reason.
- Image: store uploaded files under `public/uploads/<listing>.<ext>` and a path column; generate an SVG placeholder from the title when there is no file so seeds look like a gallery.
- Moderation predicates live here (`listingAvailability` reads active `listing_removals`; `customerStanding` reads active `customer_blocks`); the admin UI that writes those rows is FEAT-006.
- `page_view_counts(site, path_pattern, day, count)` is the rollup table FEAT-006's hook writes; create it here so the hook has a target.

## Related work
- `prototype/rails/work/3-done/FEAT-003-commerce-domain-core.md`
- `__local__/retro.md` items 1, 2, 5, 8, 10.

## Working

### What exists

**Core** (`app/core/**`, pure, sidecar-tested, no database):

| Folder           | Modules                                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `listings/`      | `listing-status` (`LISTING_STATUS_TRANSITIONS`, `canTransitionListing`, `transitionListing`), `listing-availability`            |
|                  | (`isOnStorefront`, `isPurchasable`), `listing-stock` (`stockAfterSale`, `stockAfterRestock`, `stockAfter`), `stock-change`,     |
|                  | `listing-draft` (`listingDraftErrors`, `parseListingDraft`), `listing-slug` (`slugBase`, `firstFreeSlug`),                      |
|                  | `listing-event-type`, `listing-view-window` (`viewWindowStart`, `isRecordedOncePerHour`), `favorite-change`,                    |
|                  | `placeholder-image` (`placeholderImageSvg`, `placeholderImageDataUri`, `listingImageSource`)                                    |
| `cart/`          | `cart-line` (`createCartLine`, `cartLineTotal`), `cart-quantity` (`quantityWithinStock`), `cart-totals` (`cartTotals`,          |
|                  | `checkoutTotals`)                                                                                                               |
| `orders/`        | `order-status` (transitions incl. `cancelled`, `orderStatusForPlacement`, `orderStatusAfterVerification`,                       |
|                  | `orderStatusFromCardDecision`, `orderStatusFromFulfillments`, `isCancellable`), `fulfillment-status` (+`hasDeparted`),          |
|                  | `order-stock` (`holdsStock`, `stockChangeBetween`), `payment-attempt` (`paymentAttemptFor`, `settledFulfillments`),             |
|                  | `order-payment` (`awaitsCard`, `isUnpaid`, `isPayable`), `shipment-details`, `shipping-address` (`missingAddressParts`),        |
|                  | `purchaser`                                                                                                                     |
| `payments/`      | `fake-card` (`decideCard`), `card-decision`, `decline-reason`, `payment-status`                                                 |
| `escrow/`        | `fee` (`platformFee`, `sellerNet`, 10%), `ledger-entry-type`, `ledger-movement` (`holdMovement`, `releaseMovement`,             |
|                  | `payoutMovement`), `ledger-balance` (`ledgerBalance`, `isPayable`), `payout-period` (`payoutPeriodEndingBefore`,                |
|                  | `payoutPeriodEndsAt`, `payoutPeriodCovers`, `payoutPeriodLabel`)                                                                |
| `notifications/` | `notification-message` (`itemSoldMessage`, `orderShippedMessage`, `newMessageMessage`), `recipient-type`,                       |
|                  | `notification-delivery` (port type)                                                                                             |
| `moderation/`    | `listing-removal` (`activeRemoval`, `canLiftRemoval`), `customer-standing` (`customerStanding`, `canShop`)                      |
| `analytics/`     | `page-view` (`isCountablePageView`, `pageViewDay`)                                                                              |
| (root)           | `transition-error` (`TransitionError`), `money` (FEAT-001)                                                                      |

**Tables** (migrations `20260823000001`..`20260823000007`, row types in `app/db/commerce-schema.ts`):
`listings`, `listing_events`, `favorites`, `listing_removals`, `carts`, `cart_items`,
`orders`, `order_items`, `payments`, `fulfillments`, `payouts`, `ledger_entries`,
`notifications`, `customer_blocks`, `page_view_counts`.

**Action signatures** — every one takes `ActionContext` (`app/actions/action-context.ts`)
as its first argument and runs inside one transaction:

```ts
type ActionContext = { db: AppDatabase; clock: Clock; notificationDelivery?: NotificationDelivery }

createListing(context, { sellerId, draft, imagePath? }): Promise<Listing>
updateListing(context, { listingId, draft, imagePath? }): Promise<Listing>
changeListingStatus(context, { listingId, status }): Promise<Listing>
recordListingEvent(context, { listingId, customerId, eventType }): Promise<ListingEvent | null>

currentCart(context, customerId): Promise<Cart>
cartContents({ db }, cartId): Promise<CartContents>            // { cartId, lines, totals }
addToCart(context, { cartId, listingId, quantity }): Promise<CartItem>
removeFromCart(context, { cartId, listingId }): Promise<void>
toggleFavorite(context, { customerId, listingId }): Promise<FavoriteChange>

placeOrder(context, { cartId, purchaser, shipping }): Promise<Order>
markAwaitingPayment(context, orderId): Promise<Order>
finalizeOrder(context, { orderId, cardNumber }): Promise<Order>
cancelOrder(context, orderId): Promise<Order>
rollUpOrderStatus(context, orderId): Promise<Order>
moveOrderStock(context, orderId, change): Promise<void>

markShipped(context, { fulfillmentId, carrier, trackingNumber }): Promise<Fulfillment>
confirmDelivered(context, fulfillmentId): Promise<Fulfillment>

runWeeklyPayout(context, asOf): Promise<readonly Payout[]>
sellerBalance({ db }, sellerId, occurredBy?): Promise<LedgerBalance>
ledgerMovements({ db }, occurredBy?): Promise<readonly SellerLedgerMovement[]>

notify(context, { recipientType, recipientId, message }): Promise<Notification>
markNotificationRead(context, notificationId): Promise<Notification>

activeListingRemoval({ db }, listingId): Promise<ActiveListingRemoval | null>
currentCustomerStanding({ db }, customerId): Promise<CustomerStanding>
```

**Core predicates the site tickets call** — `isOnStorefront(status, hasActiveRemoval)`,
`isPurchasable(status, quantity, hasActiveRemoval)`, `canShop(standing)`,
`awaitsCard(status)`, `isUnpaid(status)`, `isPayable(status, isPurchaserVerified)`,
`isCancellable(status)`, `canLiftRemoval(kind)`, `isShipmentComplete(details)`,
`missingAddressParts(address)`, `listingImageSource(imagePath, title)`,
`isCountablePageView(response)`.

### Decisions

- **`ActionContext` carries an optional `notificationDelivery`.** Every action
  takes the same shape, so a route builds one context and passes it everywhere;
  `notify` is the single write point, so the port has one call site.
- **`runInTransaction` joins the caller's transaction.** SQLite refuses a nested
  `BEGIN`, and `markShipped` calls `rollUpOrderStatus` calls `notify`. Kysely's
  `db.isTransaction` distinguishes a handle already inside one, so an action
  either opens a transaction or inherits the one it was handed.
- **`app/db/commerce-schema.ts` holds the commerce row types; `schema.ts` gains
  one line.** Both tickets extend `Database`; keeping the fifteen tables in
  their own file left one shared line to touch.
- **Timestamps are ISO-8601 text and dates are `YYYY-MM-DD` text.**
  better-sqlite3 binds no `Date`. Both formats sort chronologically as text, so
  the payout window is a plain `<=`.
- **Foreign keys point at `sellers` / `customers` / `admins` before those tables
  exist.** SQLite resolves a foreign key when a row is written, not when the
  table is created, so the two tickets' migrations are order-independent.
- **The `paid_out` ledger entry is dated at `payoutPeriodEndsAt`, which is
  `T23:59:59.999Z`.** Rails used `:59` with second precision; timestamps here
  carry milliseconds, so the period has to close after the last one.
- **`recordListingEvent` returns `null` when it collapses a repeat.** The
  "which events collapse per hour" rule is `isRecordedOncePerHour` in the core,
  so the action holds no event-type literal and the query narrows by the type it
  was given.
- **`moveOrderStock` writes for every change including `keep`.** `stockAfter`
  already treats `keep` as identity; guarding it in the action would put a
  domain branch back into coordination to save a no-op update.
- **`cancelOrder` restores stock through `stockChangeBetween`, not a literal.**
  Cancelling a `payment_failed` order restores nothing, because the decline
  already handed the stock back.
- **`customer-standing.ts` sits in `app/core/moderation/`, not
  `app/core/customers/`.** `docs/architecture.md` names the latter path, but
  FEAT-002 owns `app/core/customers`; the block predicate belongs with the
  removal predicate either way.
- **`activeRemoval` is generic in its row type** so a caller keeps the `id` and
  `createdAt` it read alongside the removal.

### Deviations from the Rails design

- **`Money` is not a value object.** `app/core/money.ts` (FEAT-001) is `Cents =
  number` plus functions, so every core signature takes and returns integer
  cents rather than a wrapped amount.
- **Value objects are plain types with parse/derive functions.** `ListingDraft`,
  `CartTotals`, `PaymentAttempt`, `PayoutPeriod`, `LedgerBalance` are `type` +
  functions, not `Data.define` classes; `Purchaser` carries
  `isEmailVerified: boolean` instead of `email_verified_at`.
- **`ArgumentError` becomes `RangeError`** for out-of-range quantities and
  `TypeError` for an unknown `StockChange`.
- **`cancelled` is reachable.** Rails left the transition with no caller;
  `cancelOrder` is a real action here (retro item 5), and `orders` gains a
  `cancelled_at` column.
- **The image is a path column, not Active Storage** (retro's "bring Rails in
  line with PHP"): `listings.image_path` plus `listingImageSource`, which falls
  back to an SVG generated from the title.
- **`notifications` has three recipient columns** (`seller_id`, `customer_id`,
  `admin_id`) with a check constraint that exactly one is set — this prototype
  has an admin site, which the Rails spike did not.
- **`page_view_counts`, `listing_removals`, `customer_blocks` are new** — the
  admin site (FEAT-006) writes them; the predicates that read them live here.
- **`RunWeeklyPayout` takes `asOf` explicitly** rather than reading a clock, and
  the CLI is `npm run payouts -- --as-of=DATE` rather than a rake task.
- **Not ported: `app/domain/reports/**` and most of `app/domain/shop/**`**
  (activity tallies, status labels, pagination, listing search, shop name).
  They are presentation-shaped and belong with the sites that read them
  (FEAT-004/005/006). `favorite_change` moved to `core/listings/` because it
  maps a change onto a listing event.
- **No `checkout_form` / `checkout_purchaser`.** Those parse HTTP input; the
  storefront ticket owns the zod schema at the route. `missingAddressParts` and
  `Purchaser` are the pieces they would have leaned on.

### Verified

- `make test` (`tsc --noEmit`, eslint, `node --test`): **593 tests, 593 pass,
  0 fail** across the whole project — 318 in `app/core/**`, 110 in
  `app/actions/**` (48 catalogue, 58 orders/escrow/notifications, plus the four
  lifecycle tests), the rest FEAT-001's and FEAT-002's.
- `make coverage`: **99.27% lines, 97.32% branches, 98.00% functions**, exit 0
  against the 90 / 80 gate. What is uncovered is migration `down()` bodies and a
  handful of defensive branches.
- Migrations apply from nothing in one run: 9 applied, identity first, then the
  seven commerce ones.
- Payouts CLI against a scratch database seeded with one delivered $450.00 sale:
  `npm run payouts -- --as-of=2026-08-24` prints
  `Payout period 2026-08-17 to 2026-08-23` / `seller 1 $405.00` / `1 seller(s) paid.`;
  a second run at `--as-of=2026-08-25` prints
  `No seller has a released balance for this period.` and the `payouts` table
  still holds one row. The `paid_out` entry is `-40500` at
  `2026-08-23T23:59:59.999Z`. Without the flag it settles the week before today.

### Notes for the site tickets

- Read a listing's removal with `activeListingRemoval` and a customer's standing
  with `currentCustomerStanding`, then hand the answer to `isOnStorefront` /
  `isPurchasable` / `canShop`. No page should test `liftedAt` itself.
- The seller portal's orders pages take a `fulfillments.id`, and the copy says
  "fulfillment" — `markShipped` and `confirmDelivered` both key off one.
- `finalizeOrder` refuses anything but `awaiting_payment` or `payment_failed`,
  so the pay route calls `markAwaitingPayment` first on every hit.
- The listing image is `listingImageSource(listing.imagePath, listing.title)`;
  an upload route writes the file under `public/uploads/` and passes the path.
- `page_view_counts` is waiting for FEAT-006's `onResponse` hook;
  `isCountablePageView` and `pageViewDay` are the predicates it needs.
