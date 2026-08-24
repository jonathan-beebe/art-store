---
id: FEAT-021
type: feature
status: open
created: 2026-08-23
---

# FEAT-021: Admin moderation of listings and customers, and the payout run moves to admin

## Problem
Admins can neither remove a listing from sale (temporarily for review or permanently) nor block a customer; there is no `customer_blocks` table; the weekly payout is run by a seller-portal debug button that settles every seller from inside one seller's portal, with no admin payout page. `docs/alignment.md` §5 fixes all three: `listing_removals` with `temporary | permanent` kinds and a lift, `customer_blocks` with a lift, and payouts as a platform action from `/admin/payouts`.

## Goal
Platform actions — moderating listings and customers and paying sellers — live on the admin site and nowhere else.

## Outcome
`POST /admin/listings/:id/removals` (kind, reason) takes a listing off the storefront whatever its status (browse, search, and `/art/:slug` all stop showing it), the seller reads the reason on their own listing page and cannot put it back on sale, `…/removals/lift` works for temporary and is refused for permanent, at most one active removal per listing; `POST /admin/customers/:id/blocks` (reason) removes cart add, checkout, pay, and message post while browsing, favorites, and reading threads stay open, `…/blocks/lift` restores them, at most one active block per customer; `/admin/payouts?seller=` lists payouts and `POST /admin/payouts` (optional `as_of`) runs the same weekly payout the rake task runs, idempotent per period; the seller-portal payout button is gone and the seller's earnings page keeps balances and history; tests cover each refusal; `docs/admin.md` and `docs/escrow.md` updated.

## Why it matters
Retro item 6: payouts, refunds, and seller suspension are platform actions; Rails is the only prototype where an admin cannot block a customer.

## Discovery notes
Node's `docs/admin.md` "What a removal or a block actually does" diagram is the spec, including `isOnStorefront(status, hasActiveRemoval)` and the listing transitions dropping `for_sale` while a removal stands, and `canShop` as the predicate a block turns off. PHP's `BlockCustomer`/`LiftCustomerBlock` and `ConversationPolicy` (`post` = view + `canShop`) are the closest Active-Record-style shape.

## Related work
- docs/alignment.md §5
- FEAT-009 (admin actor)
- prototype/php FEAT-010
