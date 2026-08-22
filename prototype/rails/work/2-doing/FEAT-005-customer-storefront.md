---
id: FEAT-005
type: feature
status: open
created: 
---

# FEAT-005: Customer storefront with cart, checkout, and guest verification

## Problem
Customers have an identity (FEAT-002) and the order lifecycle exists (FEAT-003), but there is no storefront.

## Goal
A first-time visitor can browse, favorite, add to cart, check out as a guest, verify their email, pay with a fake card, and watch the order ship and deliver.

## Outcome
- `/`: grid of `for_sale` listings (image, title, shop name, price), search (title/description/medium), medium filter, pagination (12). Listing page GET records a `view` event.
- `/art/:slug`: large image, details, price, quantity, Favorite/Unfavorite, Add to cart; sold listings show "Sold" and no cart button.
- `/favorites`, `/cart` (items, remove, subtotal, Checkout).
- `/checkout`: email (read-only when signed in) + shipping; for a verified signed-in customer the card fields are on the same form and the order is placed and finalized in one request. For an unverified email the order is created `pending_verification`, the page says a verification link was sent, and the debug alert shows the magic link whose `redirect_to` is `/orders/:id/pay`. Verification claims/merges the anonymous customer and lands on the pay page; the card is entered there and never stored.
- Declined cards (`4000 0000 0000 0002`, `…9995`, anything else) show the decline reason with a retry form; `4242 4242 4242 4242` succeeds.
- `/orders`, `/orders/:id`: status, items grouped by seller with fulfillment status, carrier/tracking, "Confirm delivery" per shipped fulfillment. `/orders/:id/pay` is owner-checked through `current_customer` (404 for strangers).
- `/account`: email, sign-in/out, notifications with mark-as-read.
- Cart, favorites, and orders survive verification/merge (tested).
- Shop layout: white, airy, large imagery, brand small; semantic HTML; no JavaScript. Integration tests beside each controller cover browse/search, view event, favorite toggle, cart add/remove, guest checkout through verification to paid, signed-in checkout, declined then retried card, confirm delivery, notifications.

## Why it matters
The customer half of the end-to-end test and the only place the payment mock and guest verification are exercised.

## Discovery notes
Read `docs/architecture.md`. Controllers under `app/controllers/shop/`, views `app/views/shop/`, routes inside `namespace :shop, path: ""`. Any domain `if` ("can this customer pay this order", "is this listing purchasable") is a pure function in `app/domain/` with a unit test. The PHP spike's `app/Http/Controllers/Shop/**` and `app/Domain/Shop/**` are a worked reference of the same flow and its checkout decision.
