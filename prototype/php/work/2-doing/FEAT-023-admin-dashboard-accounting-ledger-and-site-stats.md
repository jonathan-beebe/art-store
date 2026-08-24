---
id: FEAT-023
type: feature
status: open
created: 2026-08-23
---

# FEAT-023: Admin dashboard, accounting, ledger browser, and site stats

## Problem
`/admin` shows two links; there is no platform tally by status, no money summary, no per-seller reconciliation, no ledger browser, no page-view record at all, and `listing_events` writes one `view` row per GET with no collapse. `docs/alignment.md` §5 lists `/admin` (tallies for every status incl. zero rows, platform money), `/admin/accounting`, `/admin/ledger?seller=&type=`, `/admin/stats` (page views by day and route pattern, listing event tallies), and the roll-up rules.

## Goal
An operator can read the platform's state, money, and traffic from the admin site, from small tables.

## Outcome
`/admin` shows a tally for every listing/order/fulfillment status (zero rows still listed) and platform money (held, available, paid out, fees earned, fees refunded, refunded) and page views this week; `/admin/accounting` reconciles every seller; `/admin/ledger` browses entries with folded totals for the filtered set; `/admin/stats` shows page views by day (7-day window) and by route pattern plus listing event tallies; page views are rolled up at response time into `page_view_counts (site, path_pattern, day, count)` by one upsert; a listing `view` event is collapsed to one per (listing, customer, UTC hour); tests cover the tallies with empty states, the roll-up upsert, and the collapse; `docs/admin.md` gains the two diagrams.

## Why it matters
Retro item 8 and the product notes ask for traffic and accounting; without roll-ups the tables grow with traffic and the admin pages get slower every day.

## Discovery notes
Node's `pageViewRollup` `onResponse` hook and `isRecordedOncePerHour` are the reference; in Laravel a terminable middleware reading `$request->route()->uri()` for the pattern and an `upsert` on the unique `(site, path_pattern, day)` index. Database-side aggregation (RFCTR-006) is the existing idiom for the tallies.

## Related work
- docs/alignment.md §5
- RFCTR-006
- prototype/node FEAT-006
