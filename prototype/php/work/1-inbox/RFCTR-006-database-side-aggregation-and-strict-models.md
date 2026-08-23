---
id: RFCTR-006
type: refactor
status: open
created: 2026-08-23
---

# RFCTR-006: Database-side aggregation, strict models, and a scheduled payout

## Problem
Several reads hydrate whole tables to aggregate in PHP: `Seller/DashboardController.php:32-38` loads every listing to `countBy` status; `Seller/ListingActivityController.php:38-45` loads every event ever recorded for a listing to render a 14-day window; `app/Actions/Escrow/RunWeeklyPayout.php:36-43` loads the entire ledger table and groups by seller on every run and every click of the debug button; `Seller::escrowBalance()` (`app/Models/Seller.php:56-61`) lazy-loads the full ledger relation and is called from the dashboard and earnings pages; `RollUpOrderStatus.php:15` re-queries `fulfillments()->get()` instead of reading the loaded relation; `app/Console/Commands/RunWeeklyPayouts.php:27` calls `$payout->seller->displayName()` per payout (one query per seller). `AppServiceProvider::boot()` is empty, so none of these lazy loads fail loudly in tests. `ShopController::page()` runs a cart-sum and a notification-count query per storefront render and forces every storefront controller through the base class to get them. `routes/console.php` holds only the stock `inspire` closure; `payouts:run` is described as weekly in `docs/architecture.md` but is never scheduled.

## Goal
Every list and balance the portal shows is computed by the database, the app refuses silent lazy loads, and the weekly payout runs on the schedule the docs promise.

## Outcome
- The status tally, the activity timeline (bounded to its window), the payout run, and the escrow balance are grouped aggregates in SQL; the same page output and the same payout rows result, proven by the existing tests plus query-count assertions where the win is the point.
- `Model::shouldBeStrict()` (or `preventLazyLoading`) is on outside production, and the suite passes with it; the payout command and `escrowBalance` lazy loads are gone.
- `RollUpOrderStatus` reads the loaded relation.
- The storefront header counts come from a view composer on the shop layout; storefront controllers no longer need the base class for that reason.
- `payouts:run` is scheduled weekly in `routes/console.php`, and a test asserts the schedule entry.

## Why it matters
The seller dashboard and payout are the pages a reviewer clicks on the seeded data; loading the ledger table per click is what a prototype looks like when it is not meant to be read.

## Discovery notes
- `withSum` per `LedgerEntryType`, or a `selectRaw('seller_id, type, sum(amount_cents)')->groupBy(...)` feeding `LedgerBalance` the same movements, keeps the arithmetic in the core.
- `Schedule::command('payouts:run')->weeklyOn(1, '02:00')` is the Laravel 11+ shape; `PayoutPeriod` is Monday–Sunday.
- A query-count assertion can use `DB::enableQueryLog()` or Laravel's `expectsDatabaseQueryCount()`.

## Related work
- RFCTR-004
