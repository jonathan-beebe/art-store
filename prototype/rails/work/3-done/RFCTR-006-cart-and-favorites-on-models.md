---
id: RFCTR-006
type: refactor
status: resolved
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

## Working

`Cart` now holds the cart behaviour: `add(listing, quantity:, at:)` finds or
builds the line, clamps the new quantity to the seller's stock, raises
`ArgumentError` on a sold-out listing or an ask below one, and records the
`cart_add` event; `remove(listing)` drops the line; `empty?`, `item_count`,
`subtotal` and `subtotals_by_seller` are the arithmetic `Domain::Cart::CartTotals`
carried, with the seller split ordered by seller id. `CartItem` validates
`quantity` at one or more and answers `total`. `Customer` gained
`current_cart` (the cart holding the most items, else a new one),
`toggle_favorite(listing, at:)` returning `:added` / `:removed` and recording
the matching listing event, and `favorited?`.

`Orders::PlaceOrder` raises on `cart.empty?` itself with the message
`CartTotals.for_checkout` used, and reads `cart.subtotal` and
`cart.subtotals_by_seller`. The rest of it is untouched; RFCTR-007 owns it.

`current_cart` moved into the `CustomerIdentity` concern, memoised and a
`helper_method`, so `Shop::BaseController` and `Auth::BaseController` both have
it and the shop layout renders `Cart (N)` on `/login` as before.
`ShopHelper#current_cart` is gone and `cart_item_count` reads
`current_cart.item_count`. The cart and checkout views render `item.total` and
`@subtotal` in place of the totals value object; the HTML is unchanged.

Deleted: `app/actions/carts`, `app/actions/favorites`, `app/domain/cart` and
`app/domain/shop/favorite_change.rb`. Their tests moved into
`test/models/cart_test.rb`, the new `test/models/cart_item_test.rb` and
`test/models/customer_test.rb`. `test/support` and `db/seeds` call `cart.add`
and `customer.toggle_favorite`.

Left alone: `Domain::Money`, `Domain::Listings::ListingStock`, and the order,
fulfillment, escrow and notification actions.
