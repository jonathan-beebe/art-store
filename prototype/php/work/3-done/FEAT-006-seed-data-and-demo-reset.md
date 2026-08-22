---
id: FEAT-006
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-006: Seed data and demo reset command

## Problem
A fresh database has no sellers or listings, so the storefront is empty and reviewers cannot test browsing without first creating a seller and listings by hand.

## Goal
`make fresh` produces a storefront a reviewer can browse immediately, with sellers they can sign in as.

## Outcome
- `php artisan migrate:fresh --seed` creates at least 4 sellers with shop names and 20+ `for_sale` listings across several media (painting, print, ceramic, textile, sculpture), plus a few `draft` and `sold` listings, with generated placeholder artwork images that differ per listing.
- Two seeded sellers have history: a paid order awaiting shipment, a shipped order, a delivered order with released funds, and one completed payout, so the seller earnings page and the fulfillment queue have content on first load.
- One seeded verified customer owns those orders and has favorites.
- The README lists the seeded seller and customer emails and explains that signing in as any of them works via the debug magic link.
- A seeder test asserts the counts above.

## Why it matters
Reviewers judge the prototype from first load; an empty store tests nothing.

## Discovery notes
Use the FEAT-003 actions to create history rather than inserting rows directly, so seeds stay consistent with the domain rules. Placeholder images: `app/Support/PlaceholderImage.php` renders an SVG (gradient from a hash of the title, a few shapes, the title as text) and stores it under the public disk; reuse it if FEAT-004 created it. Factories for `Seller`, `Customer`, `Listing` help the feature tests too — put them in `database/factories`.

## Working

### Decisions

- **`image_path` stays null on every seeded listing.** `Listing::imageUrl()` already
  falls back to `PlaceholderImage::dataUri($title)`, which renders a different SVG
  per title with no I/O and no public-disk dependency. Writing SVGs to the public
  disk would need `storage:link`, which the entrypoint only runs once FEAT-004
  lands; leaving `image_path` null sidesteps that ordering problem entirely.
- **Three of the 24 `for_sale` listings seed at quantity 2, not 1.** Order history
  sells one unit of each (Ash-Glazed Tea Bowl, Kitchen Table Late Morning,
  Standing Figure in Reclaimed Oak) through the real actions. `ListingStock`
  only flips a listing to `sold` when it reaches zero, so quantity 2 lets the
  order consume a unit and the listing stay `for_sale` — the seeded counts
  (24 `for_sale`, 3 `draft`, 2 `sold`) hold both before and after
  `OrderHistorySeeder` runs, rather than depending on order side effects.
- **The two standalone `sold` listings and the three `draft` listings are
  unrelated to the order history.** They exist to show those statuses on the
  storefront/seller portal without coupling the seeder's listing counts to how
  many orders get placed.
- **Order history is two orders for Maya (seller 1) and one for Noah (seller 2),
  not one order per seller.** The ticket's Outcome asks for a paid order
  awaiting shipment, a shipped order, and a delivered+paid-out order across
  "two seeded sellers." Splitting 2/1 lets one seller's earnings page show a
  completed payout and their fulfillment queue show a pending shipment at the
  same time.
- **Fixed dates**: all three orders place on 2026-07-06/07 within the same
  Monday–Sunday payout period; the delivered order ships 2026-07-08 and
  delivers 2026-07-10, still inside that period. `RunWeeklyPayout` runs
  as-of 2026-07-16, the following week, so `PayoutPeriod::endingBefore`
  resolves to the completed 07-06–07-12 period and pays out only the
  delivered order's released funds (the other seller's held funds are
  untouched, since they were never released).
- **Favorites are created with `Favorite::create(...)` directly**, not through
  an action. FEAT-003 shipped `RecordListingEvent` (used here for `view` and
  `favorite` events) but no action that writes the `favorites` table itself;
  that table is a plain adapter-level record, not a domain transition.
- **`phpunit.xml` now scans `database` for `*Test.php`.** It previously scanned
  only `app` and `routes`, so `database/seeders/DatabaseSeederTest.php` was
  silently skipped ("No tests executed!") until this was added. No file in
  `database/` besides this ticket's seeders and their test exists yet, so the
  new directory only picks up files this ticket owns.

### Parallel work with FEAT-004 and FEAT-005

`routes/seller.php` referenced `ListingActivityController`, `ShipmentController`,
and `PayoutController` as invokable controllers before FEAT-004 had added them,
so the whole app failed to boot ("Invalid route action") for several retries
while that ticket was mid-flight. Waited it out with repeated
`composer test -- --filter DatabaseSeeder` runs rather than touching
`routes/seller.php`; the app became bootable once FEAT-004 landed those
controllers.

At hand-off, `make test` is red with 18 failures and 1 error, all in
`App\Http\Controllers\Shop\*` (`StorefrontControllerTest`,
`CheckoutControllerTest`, `CartControllerTest`, `OrderControllerTest`,
`OrderPaymentControllerTest`, `FavoriteControllerTest`, `ListingControllerTest`,
`AccountControllerTest`) — FEAT-005's storefront controllers and views, still
mid-build. None reference seeders, factories, or `database/**`.
`composer test -- --filter DatabaseSeeder` is green in isolation (6 tests, 25
assertions), and no other test outside `Shop\*` regressed, so FEAT-006's own
work is complete; the red suite is FEAT-005's to close out.

### Seeded accounts

- Sellers: `maya@example.com` (Terra & Glaze Ceramics), `noah@example.com`
  (North Light Editions), `priya@example.com` (Priya Anand Textile Studio),
  `leo@example.com` (Leo Martins Photography) — all verified.
- Customer: `casey@example.com` (Casey Whitfield) — verified, 3 favorites,
  6 view events, order history with Maya and Noah.
- Counts after `migrate:fresh --seed`: 4 sellers, 29 listings (24 `for_sale`,
  3 `draft`, 2 `sold`), 3 orders (1 `paid`/awaiting shipment, 1 `shipped`,
  1 `delivered`), 1 payout, 5 ledger entries (3 `held`, 1 `released`,
  1 `paid_out`), 3 payments, 5 notifications.
