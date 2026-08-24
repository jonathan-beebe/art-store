---
id: MAINT-002
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-002: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-001, FEAT-018..020, BUG-007, and IMPRV-009..012 land, `docs/` (architecture, data-model, orders, escrow, admin, identity, messaging, review) describe the pre-alignment code, `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; `make docs-check` renders every diagram; every doc under `docs/` and the README state what the code does after alignment, with `docs/review.md` listing the known gaps that remain; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-017 is the pattern: an independent audit agent reads `docs/` against `src/app/` and lists mismatches before anyone rewrites.

## Related work
- FEAT-017
- docs/alignment.md

## Working

### Fix-up

Alignment cross-check against the PHP prototype found `GET /cart` rendering a
listing an admin removed after it was already in the customer's cart: title,
image, and price intact, a working Remove link, and a View link to a page
that now 404s. Every other storefront surface already filters removals
(`isBrowsable`, `findListingOnStorefront`, `findFavoriteListings`); this was
the one gap. Fixed by mirroring the concept `checkout.ts` already has rather
than dropping the line silently:

- `order-placement.ts` (core) exports its already-private `unavailableReason`
  and a new `noticeForUnavailableReason`, so a caller other than
  `planOrderPlacement` can ask the same question about a single line.
- `cart-contents.ts` (shell) reads each line's active removal the same way
  `place-order.ts`'s `withRemovals` does, and stamps `isUnavailable` /
  `unavailableNotice` on the `CartLineView`. All four reasons apply (removed,
  off sale, sold out, short stock), not just removal — the same set checkout
  already refuses on, so the cart and checkout agree on what "unavailable"
  means rather than the cart inventing a narrower one.
- The cart total: unavailable lines are excluded from `cartTotals`, computed
  over `lines.filter(l => !l.isUnavailable)` rather than every line. A
  subtotal that included a line checkout will refuse to sell would be a
  number the customer could never actually pay.
- `cart.ejs`: an unavailable line keeps its Remove form (already worked,
  since `POST /cart/:slug/remove` resolves by slug through
  `findListingBySlug`, not the storefront-filtered query) but loses its
  `/art/:slug` link and shows the notice text in place of quantity and price.

Order pages and message threads were not touched — both are historical
records and already show removed listings on purpose.

Tests added: `cart-contents.test.ts` (removed / off-sale / normal lines,
and the total excluding an unavailable one) and `carts.test.ts` (`GET /cart`
renders the marked row, Remove still works, no dead `/art/:slug` link, and
the subtotal excludes the removed line's price).

`make check` green: 1915 tests (1906 baseline + 9), coverage 99.43/95.92/99.50
lines/branches/functions (baseline 99.42/95.94/99.49).
