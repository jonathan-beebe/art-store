---
id: FEAT-006
type: feature
status: open
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
