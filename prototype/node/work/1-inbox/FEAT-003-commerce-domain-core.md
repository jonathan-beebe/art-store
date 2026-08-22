---
id: FEAT-003
type: feature
status: open
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
