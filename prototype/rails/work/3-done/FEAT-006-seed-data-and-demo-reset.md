---
id: FEAT-006
type: feature
status: resolved
created: 
---

# FEAT-006: Seed data and demo reset

## Problem
A fresh database has no sellers or listings, so the storefront is empty.

## Goal
`make fresh` produces a storefront a reviewer can browse immediately, with sellers they can sign in as.

## Outcome
- `bin/rails db:seed` creates 4 sellers, 24 `for_sale` listings across painting, print, ceramic, textile, sculpture, photography, plus 3 `draft` and 2 `sold`, each with a generated placeholder SVG image that differs per listing.
- Order history built through the FEAT-003 actions: one paid order awaiting shipment, one shipped, one delivered with released funds, and one completed payout; one verified customer `casey@example.com` owns them and has favorites and view events.
- Seeds are deterministic (fixed data, fixed dates in July 2026) and idempotent enough to re-run after `db:reset`.
- README lists the seeded emails (`maya@`, `noah@`, `priya@`, `leo@example.com`, `casey@example.com`) and explains sign-in via the debug magic link.
- A sidecar test `db/seeds_test.rb` (or `db/seeds/seeds_test.rb`) asserts the counts.

## Why it matters
Reviewers judge the prototype from first load.

## Discovery notes
Split seeds into `db/seeds/*.rb` files loaded by `db/seeds.rb`. Placeholder art: `app/support/placeholder_image.rb` renders an SVG from a hash of the title (pure, sidecar-tested); attach it as an Active Storage blob or expose `Listing#image_url` that falls back to a data URI. The PHP spike's `database/seeders/**` and `app/Support/PlaceholderImage.php` are a worked reference.

## Working

### Decisions

- **Images are left unattached.** `Listing#image_url` already falls back to
  `PlaceholderImage.data_uri(title)`, which hashes the title, so every
  listing already gets a distinct generated SVG with no Active Storage
  writes in the seed run. Attaching real blobs would add I/O and slow the
  seed for no visible difference on the storefront.
- **`db/seeds.rb` is idempotent by short-circuiting**, not by upserting: it
  returns early if `Seller.exists?`. A fresh database always seeds; a
  database that already has sellers is left alone rather than raising on
  the unique email index. `db/seeds_test.rb` asserts a second
  `Rails.application.load_seed` in the same run adds nothing.
- **`db/seeds/customers.rb` calls `::Listings::RecordListingEvent`** (leading
  `::`). `db/seeds/listings.rb` defines `Seeds::Listings` for the seed data
  itself, so an unqualified `Listings::RecordListingEvent` inside
  `Seeds::Customers` resolves to the seed module first and raises
  `NameError`. The leading `::` reaches the top-level `Listings` actions
  namespace instead.
- **`bin/rails test app lib` does not glob `db/`.** Added `db` to the
  `test` and `coverage` targets in the `Makefile` and documented it in the
  README's Tests section, per the ticket's fallback instruction.
- **Order history matches the PHP spike's `OrderHistorySeeder` exactly**:
  Ash-Glazed Tea Bowl (Maya) paid and left awaiting shipment; Kitchen
  Table, Late Morning (Noah) paid and shipped; Standing Figure in
  Reclaimed Oak (Maya) paid, shipped, and delivered, then
  `Escrow::RunWeeklyPayout` as-of 2026-07-16 pays out Maya's released
  balance from the delivered order. Two sellers appear across the three
  fulfillments, matching the ticket's "one completed payout."

### Verified

- `docker compose run --rm app bin/rails test app lib db`: 438 runs, 767
  assertions, 0 failures, 0 errors. `db/seeds_test.rb` alone: 7 runs, 24
  assertions.
- `docker compose run --rm app bin/rails zeitwerk:check`: all is good.
- Did not run `make fresh` / `db:seed` against the shared development
  SQLite file — FEAT-004 and FEAT-005 were working in the same tree and
  may depend on its current state. `db/seeds_test.rb` runs the same
  `Rails.application.load_seed` against the test database, which is the
  behavior that matters. **Request for FEAT-008**: run `make fresh` once
  all tickets land, as the first true end-to-end check of `db:seed`
  against a dropped-and-recreated database.

### Notes for the tickets that follow

- Seeded emails: `maya@example.com`, `noah@example.com`,
  `priya@example.com`, `leo@example.com` (sellers), `casey@example.com`
  (customer). All pre-verified, so the debug magic link signs straight in.
- 29 listings total (24 `for_sale`, 3 `draft`, 2 `sold`); three `for_sale`
  listings (`Kitchen Table, Late Morning`, `Ash-Glazed Tea Bowl`,
  `Standing Figure in Reclaimed Oak`) start at quantity 2 because order
  history sells one unit of each.
