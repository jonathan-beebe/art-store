---
id: FEAT-024
type: feature
status: open
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
