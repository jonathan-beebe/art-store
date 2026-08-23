---
id: RFCTR-011
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-011: Dissolve the Domain namespace and the custom autoloader

## Problem
`src/config/application.rb` removes `app/domain` from the eager-load paths and re-pushes it under a `Domain` namespace in a custom initializer; `app/domain`, `app/actions`, `app/delivery` and `app/support` are directories Rails does not have, and every status string is reached through a constant (`Domain::Orders::OrderStatus::PAID`) where the `enum` already provides `Order.paid` / `order.paid?` / `"paid"`.

## Goal
The application tree is the stock Rails tree and the models are the domain.

## Outcome
`config/application.rb` has no autoloader customisation; `app/domain`, `app/actions`, `app/delivery` and `app/support` do not exist; value objects that survive (money, payout period, page, placeholder image, fake card) live in `app/models` as plain Ruby classes; status references use the enum API or the bare string; `bin/rails zeitwerk:check` passes; the suite passes unchanged; `docs/architecture.md` describes the resulting layers.

## Why it matters
The custom namespace is the single largest thing a Rails developer has to learn here, and it exists only to host the service/domain split the other refactor tickets remove.

## Discovery notes
This is the closing ticket once RFCTR-004 through RFCTR-010 have emptied the directories. The SimpleCov groups in `test/test_helper.rb` name the old directories.

## Related work
- RFCTR-004
- RFCTR-005
- RFCTR-006
- RFCTR-007
- RFCTR-008
- RFCTR-009
- RFCTR-010

## Working

The directories left to dissolve were `app/domain` (money, transition_error,
`shop/{listing_search,page,shop_name}`, `reports/*`) and `app/support`
(placeholder_image). `app/actions` and `app/delivery` were already gone, so
nothing extra had to be folded in.

Four files moved into `app/models` unchanged apart from losing their wrapper
module: `Money`, `TransitionError`, `Page` and `PlaceholderImage`. With
`app/domain` gone, `config/application.rb` lost the `module Domain`, the
`eager_load_paths -=` line and the `art_store.autoloading` initializer;
`field_error_proc` stayed. `bin/rails zeitwerk:check` passes on the defaults.

The rest folded into the records that own the data:

- `Domain::Shop::ListingSearch` became `Listing.search(term:, medium:)`,
  `Listing.like_pattern` and `Listing.media_for_sale`, so `TEXT_MATCH` sits
  next to the query and `Shop::StorefrontController#show` reads the two
  filters straight off `params`.
- `Domain::Shop::ShopName.of` became `Seller#display_name`, which replaced
  `ShopHelper#shop_name_of` at its four view call sites.
- `Domain::Reports::ActivityTotals` and `DailyActivity` became
  `ListingEvent::Totals` and `ListingEvent::Day`, reached through
  `Listing#activity_totals`, `Listing#activity_by_day(days:, ends_on:)` and
  `ListingEvent.totals_by_listing`. The last one keeps the seller index at one
  grouped query rather than one per row.
- `Domain::Reports::ListingStatusTally` became `Seller#listing_status_counts`,
  an array of `[status, count]` in `Listing.statuses.keys` order, and the
  dashboard labels a tile with `status_label`. `ListingStatusTally.total` had
  no caller outside its own test and went with it.

`ShopHelper#money` still reads `Money.from_cents(cents).format`.

The rendered HTML is unchanged: the dashboard tiles keep their `data-stat`
keys, the listing activity table keeps `data-day` and `data-activity`, and the
storefront keeps the heading, the hidden `q` field and the medium select.

Tests: `test/domain` is gone. `money_test.rb` and `page_test.rb` moved to
`test/models` and lost their namespace, the reports tests became
`test/models/listing_event_test.rb` plus the activity tests in
`listing_test.rb`, the search tests became database-backed `Listing.search`
tests, and the shop-name tests became `display_name` tests in `seller_test.rb`.
The SimpleCov groups are Models, Controllers, Helpers and Mailers. 527 runs,
1604 assertions, 0 failures, 100% line coverage.

Docs: `docs/architecture.md` "Layers inside the deployable" is now the stock
Rails shape, and the listing-status, commerce, testing and skills sections name
the new constants. `docs/ontology.md`, `docs/orders.md` and `docs/review.md`
lost their `Domain::` references, and `README.md` lost `app/domain` from the
layout block and the test paths. The stale run count in `docs/review.md` (645)
and `README.md` (531) is now the measured 527.

Left alone: `Notification#deliver_by_email` is still an empty hook, and the
`Seller` class/module collision note in `docs/architecture.md` still stands.
