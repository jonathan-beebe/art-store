---
id: FEAT-004
type: feature
status: open
created: 2026-08-22
---

# FEAT-004: Seller portal — listings, activity, fulfillments, earnings, notifications

## Problem
`/seller` is a placeholder. Sellers need the back-office the brief describes: create and manage listings, see activity on them (views, favorites, cart adds), see what sold and ship it, and read earnings and payouts.

## Goal
An artist signs in, lists a piece, marks it for sale, and later ships the sale and reads what they are owed — all from `/seller`, in a plain tool-focused UI.

## Outcome
- Dashboard at `/seller`: listing counts by status, recent activity, open fulfillments, available and held balance, unread notifications.
- Listings: index, new, edit, show; image upload; status buttons that follow the core transitions and refuse illegal ones with a message; a removed listing shows the admin's removal kind and reason and cannot be put back on sale by the seller.
- Listing activity page: totals and a 14-day daily breakdown of views / favorites / cart adds.
- Fulfillments ("Orders" in the nav, "fulfillment" in copy): grouped by status; detail page with the customer's shipping address and a mark-shipped form (carrier + tracking) that notifies the customer.
- Earnings: balance (held / available / paid out), sold-goods report, payout history. No payout button — the run is an admin action.
- Notifications inbox with mark-as-read.
- Every page is behind `requireSeller`; another seller's listing or fulfillment id answers 404 on reads and writes.
- Each page has an integration test; listing status refusal and cross-seller 404s are tested.

## Why it matters
This is half the product and the half the showdown's reviewers will click through first.

## Discovery notes
Reference: `prototype/rails/src/app/controllers/seller/**`, `app/views/seller/**`, `app/domain/reports/**`. Stock Tailwind, system font, semantic HTML, vanilla controls — tables, forms, buttons; no component library.
- Routes parse bodies with zod and call FEAT-003 actions; a report needs a pure function under `app/core/reports/` (activity totals, daily timeline, status tally, payout summary) fed rows by the route.
- Prices are entered in dollars and stored in cents (`parseDollars`); show field errors next to fields.
- Multipart upload via `@fastify/multipart`; accept png/jpg/webp; write to `public/uploads`.
- Touch only `app/sites/seller/**`, `app/core/reports/**`, and one registration line in `app/app.ts`. FEAT-005 / FEAT-006 / FEAT-008 run in parallel in the same tree — commit with an explicit pathspec.

## Related work
- `prototype/rails/work/3-done/FEAT-004-seller-portal.md`
- `__local__/retro.md` item 10 (say "fulfillment" where the row is one).
