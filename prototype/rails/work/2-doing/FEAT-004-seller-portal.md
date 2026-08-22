---
id: FEAT-004
type: feature
status: open
created: 
---

# FEAT-004: Seller portal for listings, activity, fulfillment, and earnings

## Problem
Sellers have a login (FEAT-002) and a domain (FEAT-003) but no screens.

## Goal
An artist can go from a fresh account to a listed, sold, shipped, and paid-out item using only the portal.

## Outcome
- `/seller` dashboard: listing counts by status, fulfillments awaiting shipment, held and available balance, unread notifications, five most recent notifications.
- `/seller/listings`: table with thumbnail, status, price, quantity, views, favorites, cart adds; actions to create, edit, and change status (only transitions `ListingStatus` allows render; a disallowed one is a validation error). Create/edit form: title, description, medium, dimensions, price in dollars (stored as cents via `Domain::Money.from_dollars`), quantity, image upload (Active Storage, content-verified). Inline validation errors.
- `/seller/listings/:id`: activity totals and a 14-day daily breakdown of views, favorites, cart adds; sales of this listing.
- `/seller/orders`: this seller's fulfillments grouped by status; `/seller/orders/:id` shows shipping address, items, mark-shipped form (carrier, tracking) or the shipped/delivered timestamps.
- `/seller/earnings`: per-fulfillment rows (date, order, items, subtotal, fee, net, status), balances (held, available, paid out), payouts table, and a debug "Run weekly payout now" button.
- `/seller/notifications` with mark-as-read.
- Seller layout, semantic HTML, stock Tailwind, no JavaScript, plain POST forms with CSRF.
- Integration tests beside each controller: unauthenticated redirect, happy path per page, create/edit/status validation, mark-shipped, payout run, and cross-seller isolation (404).

## Why it matters
This is the artist's whole back office; half of the end-to-end test.

## Discovery notes
Read `docs/architecture.md`. Controllers under `app/controllers/seller/`, views `app/views/seller/`, routes inside `namespace :seller`. Scope every query through `current_seller.listings` / `current_seller.fulfillments` so authorization is the query. Reporting math (balances, daily breakdown, status tallies) lives in `app/domain/reports/` as pure functions with sidecar tests. The PHP spike's `app/Http/Controllers/Seller/**` and `app/Domain/Reports/**` are a worked reference.
