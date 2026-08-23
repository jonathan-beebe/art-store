---
id: RFCTR-013
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-013: Resourceful storefront controllers behind the existing URLs

## Problem
`src/config/routes.rb` maps storefront verbs to custom actions: `carts#add`/`carts#remove`, `favorites#toggle`, `order_payments#show/create`, `notifications#update` for marking read, `delivery_confirmations#create` with an `:id` that is a fulfillment. The seller side already uses nested singular resources (`listing_statuses`, `shipments`, `notification_reads`).

## Goal
Every storefront controller is a resource with only the seven standard actions.

## Outcome
Cart lines, favorites and read-marks are `create`/`destroy` on their own controllers; the public URL paths and the HTML forms that post to them are unchanged; the storefront integration tests and the smoke test pass without edits to their request lines.

## Why it matters
Custom actions are where controllers grow; the seven-action constraint is the convention the rest of the app already follows.

## Discovery notes
Explicit `post "cart/:slug" => "cart_items#create"` lines keep the paths while the controllers become resources. The favorite toggle needs the button to know whether to `create` or `destroy`; the listing page already knows `@favorited`. Lowest priority of the set; URLs are user-facing, so keep them byte-identical.

## Related work
- RFCTR-006

## Working

`Shop::CartItemsController#create`/`#destroy` replace `Shop::CartsController#add`/`#remove`, behind
`post "cart/:slug"` and `delete "cart/:slug"` with the `add_to_cart` and `remove_from_cart` helper
names kept by `as:`. A `set_listing` before_action holds the lookup both actions share, and
`SOLD_OUT` moved with the action that reads it. `Shop::CartsController` is left with `show`.

`Shop::NotificationReadsController#create` replaces `Shop::NotificationsController#update` behind
`post "account/notifications/:id/read"`, mirroring `Seller::NotificationReadsController`. The
`notifications_controller.rb` file is deleted.

`Shop::BaseController#now` is deleted. The four callers passed it to a model method whose `at:`
keyword already defaults to `Time.current`, so they drop the argument.

Favorites keep `toggle`. `Shop::FavoritesControllerTest` posts twice to the same path and asserts
the second post removes the favorite and records an `unfavorite` event, which only a toggle answers;
a `create`/`destroy` split would need that request line edited, and the ticket makes the tests the
contract. The listing page button also stays a bare POST, so its HTML is unchanged.

`Shop::OrderPaymentsController` and `Shop::DeliveryConfirmationsController` are left alone, as the
task says.

Tests: `test/controllers/shop/cart_items_controller_test.rb` takes the six add and remove cases from
`carts_controller_test.rb`, which keeps the four that exercise `show` and the header;
`test/controllers/shop/notification_reads_controller_test.rb` takes the two read-mark cases from
`account_controller_test.rb` with the `notify` helper they use. No assertion changed. 527 runs, 1604
assertions, 0 failures at 100% line coverage; `zeitwerk:check` passes.

Docs: `docs/review.md` names the two new test classes in the customer-site and fulfillment tables,
and gap 3 drops `Shop::NotificationsController` (17 files to 16, verified against the tree) along
with the suggested next step that asked for its test.
