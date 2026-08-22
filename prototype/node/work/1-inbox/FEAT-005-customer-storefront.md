---
id: FEAT-005
type: feature
status: open
created: 2026-08-22
---

# FEAT-005: Customer storefront — browse, favorite, cart, guest checkout, pay, orders

## Problem
`/` is a placeholder. Customers need to browse hand-made art, favorite and cart pieces anonymously, check out as a guest with email verification before the card, pay with the fake card (success and failure), watch their order ship, confirm delivery, and cancel before payment.

## Goal
A first-time visitor goes from the home page to a paid order without creating an account up front, and the whole flow reads as bright, open, and about the art.

## Outcome
- Home: paged grid of `for_sale`, un-removed listings with large imagery; search by text and filter by medium.
- Listing page `/art/:slug`: image, title, seller's shop name, price, description, published FAQs (FEAT-007 fills them; render an empty state now), favorite toggle, add to cart; a view event is recorded; `sold` shows as sold out; draft / archived / removed answer 404.
- Favorites page; cart page with quantities, remove, totals.
- Checkout: email + shipping address; a verified signed-in customer also enters the card on the same form and lands on the order page paid; a guest gets a magic link (debug alert) whose redirect is `/orders/:id/pay`, where the card form appears after verification.
- Pay page: 4242 pays; a decline shows the reason and a retry form with the stock returned; a blocked customer cannot check out and is told so.
- Orders list and order page with per-seller fulfillments, carrier + tracking once shipped, a confirm-delivery button, and a cancel button while cancellable.
- Account page with notifications and mark-as-read; sign-out.
- Integration tests walk guest checkout, signed-in checkout, decline-then-retry, cancel, and the 404 cases.

## Why it matters
The customer flow is the demo; guest checkout with verify-before-card is the ordering a hosted payment element will need in the real product.

## Discovery notes
Reference: `prototype/rails/src/app/controllers/shop/**`, `app/views/shop/**`, `app/domain/shop/**`, and `docs/orders.md`. Theme: white background, generous spacing, one accent, images first, the site name small.
- All rules come from FEAT-003 actions and predicates; the route decides nothing about the domain. Search / paging are pure helpers under `app/core/shop/`.
- The storefront `preHandler` from FEAT-002 supplies `currentCustomer`; a blocked customer is a core predicate, render a notice.
- Touch only `app/sites/shop/**`, `app/core/shop/**`, and one registration line in `app/app.ts`. FEAT-004 / FEAT-006 / FEAT-008 run in parallel — commit with an explicit pathspec.

## Related work
- `prototype/rails/work/3-done/FEAT-005-customer-storefront.md`
- `__local__/retro.md` items 3 and 5.
