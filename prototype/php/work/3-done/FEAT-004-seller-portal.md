---
id: FEAT-004
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-004: Seller portal for listings, activity, fulfillment, and earnings

## Problem
Sellers have a login (FEAT-002) and a domain (FEAT-003) but no screens. They cannot create listings, see activity, fulfil orders, or read earnings.

## Goal
An artist can go from a fresh account to a listed, sold, shipped, and paid-out item using only the portal.

## Outcome
- `/seller` dashboard: counts of listings by status, open fulfillments awaiting shipment, held and available balance, unread notifications, and the five most recent notifications.
- `/seller/listings`: table of the seller's listings with status, price, quantity, views, favorites, cart adds; actions to create, edit, mark for sale, mark draft, archive. Create/edit form has title, description, medium, dimensions, price (dollars input, stored as cents), quantity, image upload (stored on the public disk; a generated placeholder when absent). Validation errors render inline next to fields.
- `/seller/listings/{listing}`: activity detail — totals and a daily breakdown for the last 14 days of views, favorites, and cart adds; sales of this listing.
- `/seller/orders`: fulfillments for this seller grouped by status; `/seller/orders/{fulfillment}` shows the buyer's shipping address, the items, and a form to mark shipped (carrier, tracking). Shipped and delivered fulfillments show their timestamps.
- `/seller/earnings`: sales report (each fulfillment: date, order, items, subtotal, fee, net, status) plus balances (held, available, paid out) and a payouts table (period, amount, status). A debug "Run weekly payout now" button calls `RunWeeklyPayout` and shows the result.
- Notifications list at `/seller/notifications` with mark-as-read.
- Every page uses the seller layout, semantic HTML (`<table>`, `<form>`, `<fieldset>`, `<label for>`), stock Tailwind, no JavaScript. All forms are plain POST with CSRF.
- HTTP feature tests beside each controller cover: unauthenticated redirect, the happy path of each page, listing create/edit/status change validation, mark-shipped, and payout run. A seller cannot see or edit another seller's listings or fulfillments (tested).

## Why it matters
This is the artist's entire back office; it is half of the end-to-end test the team needs.

## Discovery notes
Read `docs/architecture.md`. Controllers in `app/Http/Controllers/Seller/`, views in `resources/views/seller/`, routes in `routes/seller.php` under the `auth.seller` middleware. Call actions from FEAT-003; do not re-implement domain rules in controllers — reporting math (balances, daily breakdown) belongs in `app/Domain/Reports` as pure functions over arrays with sidecar tests. Authorization: scope every query through `auth('seller')->user()->listings()` etc., or a policy. Image placeholder: a small SVG generated from the listing title (see FEAT-006 for the same generator; share `app/Support/PlaceholderImage.php` if it exists, otherwise create it). Keep the dollars-to-cents conversion as a pure function with tests.

## Working

### Routes (`routes/seller.php`)

Every route sits under `auth.seller`, prefix `/seller`, name prefix `seller.`.

| Name | Method | Path |
| --- | --- | --- |
| `seller.dashboard` | GET | `/seller` |
| `seller.listings.index` | GET | `/seller/listings` |
| `seller.listings.create` | GET | `/seller/listings/create` |
| `seller.listings.store` | POST | `/seller/listings` |
| `seller.listings.show` | GET | `/seller/listings/{listing}` |
| `seller.listings.edit` | GET | `/seller/listings/{listing}/edit` |
| `seller.listings.update` | POST | `/seller/listings/{listing}` |
| `seller.listings.status` | POST | `/seller/listings/{listing}/status` |
| `seller.orders.index` | GET | `/seller/orders` |
| `seller.orders.show` | GET | `/seller/orders/{fulfillment}` |
| `seller.orders.ship` | POST | `/seller/orders/{fulfillment}/shipment` |
| `seller.earnings` | GET | `/seller/earnings` |
| `seller.earnings.payout` | POST | `/seller/earnings/payouts` |
| `seller.notifications.index` | GET | `/seller/notifications` |
| `seller.notifications.read` | POST | `/seller/notifications/{notification}/read` |

### Decisions

- **No implicit route-model binding in the portal.** Every controller opens
  with `auth('seller')->user()->listings()->findOrFail($id)` (or
  `fulfillments()`, `notifications()`). Cross-seller access is a 404 on every
  route with no per-controller ownership check to forget, and the scoped query
  is the authorization. No policies were added.
- **The status form is validated against `ListingStatus::transitions()`.** The
  rule is built from the loaded listing, so the index renders only the buttons
  the current status allows and a posted transition outside that set comes back
  as a `status` validation error. The domain still guards it: the action calls
  `transitionTo()`, which throws.
- **`ListingStatusController` and `ShipmentController` are separate from the
  page controllers.** A status change and a shipment are commands with their
  own validation; folding them into `ListingController`/`OrderController` would
  put four unrelated jobs behind one class.
- **`OrderController` renders `Fulfillment` rows.** A seller's order is the
  slice of a customer order that belongs to them, which is exactly one
  fulfillment. The URLs say `orders` because that is the seller's word for it;
  the class doc block maps the two.
- **Update leaves the slug alone.** A renamed listing keeps the storefront URL
  it was shared under. `CreateListing` picks the first free slug for the title.
- **Update deletes the image file it replaces.** Nothing else references it, and
  orphans on the public disk are invisible until the disk fills.
- **`ShipmentController` returns 422 for a fulfillment that has already
  shipped**, rather than letting `MarkShipped` throw a `DomainException` into a
  500.
- **`PayoutController` runs `RunWeeklyPayout(now)` for every seller**, not just
  the signed-in one — that is what the action does, and the button is a debug
  control for the whole prototype. The flash says so.
- **Uploads are validated with `mimetypes:` rather than `image:`.** The
  container has no GD extension, so `UploadedFile::fake()->image()` cannot be
  used in tests; `->create(..., 'image/jpeg')` plus a mimetype rule tests the
  real path.

### Domain added (`app/Domain/Reports/**`)

- `ActivityTimeline::lastDays(countsByDate, endsOn, days)` → `list<DailyActivity>`,
  gapless and oldest-first.
- `DailyActivity` — one day's views / favorites / cart adds, with `total()` and
  a `label()` for the table row.
- `ListingStatusTally::from(countsByStatus)` → `list<ListingStatusCount>`, every
  status in lifecycle order, zero-filled; `::total()` sums them.
- `ListingStatusCount` — a status and its count.
- `StatusLabel::of(BackedEnum)` — `for_sale` → `For sale`. Used for listing,
  fulfillment, and order statuses.
- `PayoutSummary::of(amountsInCents)` — count and total for the payout-run flash.

### Deviations from the ownership list

- `app/Domain/Money/Money.php` gained `Money::fromDollars(string)` with tests in
  the existing `MoneyTest.php`. It parses the string rather than multiplying a
  float, so a large price keeps every cent.
- `app/Domain/Listings/` gained two new files: `ListingSlug` (pure
  slug-collision resolution, sidecar test) and `ListingDraft` (the validated
  form fields, carried into the actions). Both are new files; no FEAT-003 file
  in that directory was edited.
- `app/Models/Seller.php` gained the `listings`, `fulfillments`,
  `ledgerEntries`, `payouts`, and `notifications` relations plus
  `escrowBalance()`, which folds the seller's ledger entries through
  `LedgerBalance`. Additive.
- `docker/entrypoint.sh` runs `php artisan storage:link --force` after migrate.
  Without `--force` the command exits non-zero when the link already exists,
  which `set -e` turns into a failed start.

### Tests

129 tests for this ticket. Full suite green at hand-off: 468 tests, 1019
assertions (FEAT-005 and FEAT-006 were mid-flight in the same tree).
