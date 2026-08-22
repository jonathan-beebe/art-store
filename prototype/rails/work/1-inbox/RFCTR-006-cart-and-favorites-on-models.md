---
id: RFCTR-006
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-006: Cart and favorites behaviour on Cart and Customer

## Problem
`Carts::AddToCart`, `Carts::RemoveFromCart`, `Carts::CurrentCart` and `Favorites::ToggleFavorite` under `src/app/actions` wrap one-line `Cart`/`Favorite` operations; `Domain::Cart::{CartLine,CartQuantity,CartTotals}` and `Domain::Shop::FavoriteChange` hold the arithmetic and the toggle decision. `ShopHelper#current_cart` duplicates `Shop::BaseController#current_cart`.

## Goal
`cart.add(listing)` and `customer.toggle_favorite(listing)` read as the domain they are.

## Outcome
Cart quantity clamping, subtotals by seller, the "cart with the most items" rule and the favorite toggle are methods on `Cart`, `CartItem` and `Customer`; the four actions and the five domain files are gone; one `current_cart` definition serves both the controller and the layout; the cart and favorites integration tests pass unchanged.

## Why it matters
A model that is only associations pushes every reader into `app/actions` to learn what a cart does.

## Discovery notes
`CartTotals.for_checkout` (raises on an empty cart) is consumed by order placement (RFCTR-007); keep the behaviour reachable. `Customer#current_cart` memoised per request covers the helper/controller duplication.

## Related work
- RFCTR-005
- RFCTR-007
- RFCTR-011
