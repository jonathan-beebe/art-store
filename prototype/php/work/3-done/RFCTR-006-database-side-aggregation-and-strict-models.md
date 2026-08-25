---
id: RFCTR-006
type: refactor
status: resolved
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

## Working

Re-verified every Problem line against the tree after RFCTR-002..005: all of
them still held. `ShopController::page()` was still there (no constructor, no
`CurrentCart` — it read `$visitor->currentCart()`), `Seller::escrowBalance()`
was still on `App\Models\Seller`, and both seller controllers were still
folding in PHP.

### What each read became

| Read              | Before                                         | After                                                                         |
| ----------------- | ---------------------------------------------- | ----------------------------------------------------------------------------- |
| Status tally      | every listing hydrated, `countBy` in PHP       | `Listing::countedByStatus()` scope (`select status, count(*)`,                |
|                   |                                                | `group by status`) read by `Seller::listingCountsByStatus()`                  |
| Activity timeline | every event ever recorded, grouped in PHP      | `ListingEvent::dailyCountsSince($from)` scope (`date(occurred_at)`, `type`,   |
|                   |                                                | `count(*)`, bounded by the window) read by                                    |
|                   |                                                | `Listing::eventCountsByDateSince()`                                           |
| Escrow balance    | `$this->ledgerEntries` (whole relation, lazy)  | `LedgerEntry::totalledByType()` scope, one row per (seller, type)             |
| Payout run        | whole ledger table hydrated and grouped in PHP | same scope, bounded by `occurredBy($period->end)`                             |

`LedgerBalance::from(list<LedgerMovement>)` is unchanged: a ledger fold only
adds amounts of the same type together, so one summed row per type folds to
the same balance as every entry behind it. No `fromTotals` was needed, and
`LedgerEntry::toMovement()` / `LedgerMovement::of()` stay in use.

The window bound for the timeline comes from the domain: `ActivityTimeline`
gained a public `firstDay(DateTimeImmutable $endsOn, int $days)` that
`lastDays()` now calls, so the SQL bound and the rendered rows read the same
definition of the window.

### Aggregate columns and the analyzer

`count(*) as tally` and `date(occurred_at) as day` are not columns, so
Larastan reports `property.notFound` on them and `level: max` refuses a
`(int)`/`(string)` cast of `mixed`. Two ways out were tried: reading the rows
off `->toBase()` as `stdClass` (kills `property.notFound` but leaves the casts
as mixed, and loses `preventAccessingMissingAttributes` and the enum casts),
and declaring the aliases as `@property-read` on the models. The second won:
`type`/`status` keep their enum casts, `tally` and `day` are typed, and a read
of either on a row the scope did not select still raises at runtime under
strict models. Both docblocks say the property only exists on a row the scope
selected.

### Strict models

`Model::shouldBeStrict(! $this->app->isProduction())` in
`AppServiceProvider::boot()`. Seven tests failed on it and were fixed at the
source, not worked around:

- `RollUpOrderStatus` now reads `$order->fulfillments`; `MarkShipped` and
  `ConfirmDelivered` `load('order.fulfillments')` after the write (same query
  count as before — the lazy `$fulfillment->order` plus the re-query became
  two explicit loads), and `RollUpOrderStatusTest` hands the action a loaded
  order.
- `SignOutController` tripped `preventAccessingMissingAttributes` on
  `remember_token`: `SessionGuard::logout()` reads it, and a factory-made
  model has no value for a column the factory never set.
  `SellerFactory`/`CustomerFactory` now set `remember_token`, which is what
  Laravel's own `UserFactory` does.
- `RunWeeklyPayouts` eager-loads the sellers it is about to name
  (`Collection::make($payouts)->load('seller')`) instead of one query per
  payout.

### Header counts

`App\View\Composers\ShopLayoutComposer` bound to `layouts.shop` in
`AppServiceProvider::boot()`; `ShopController::page()` is gone and the eight
storefront pages call `view()`. The composer no-ops when
`CustomerIdentity::current()` is null, which is how `/login` renders the same
layout off the storefront.

RFCTR-008 moves layouts to components; the composer is named on
`layouts.shop`, the view name that exists now, and that binding is the one
line to move.

Composer data lands on the layout's view instance, not the page's, so
`assertViewHas('cartItemCount', ...)` no longer sees it — the sidecar asserts
the rendered header instead. No existing test asserted on that view data.

`StorefrontController` is the one storefront controller that now uses nothing
from `ShopController`. It still extends it: the base class is the storefront's
by site, and the Outcome is about not being forced through it for the counts.

### Schedule

`Schedule::command('payouts:run')->weeklyOn(1, '02:00')` in
`routes/console.php`; `routes/consoleTest.php` reads
`app(Schedule::class)->events()` and asserts the one `payouts:run` event has
expression `0 2 * * 1`. `tests/Pest.php` gained a `Tests\TestCase` binding for
`../routes` and a `Tests\StorefrontTestCase` binding for
`../app/View/Composers`.

### Measured

| Page / run                                              | Queries                                                 |
| ------------------------------------------------------- | ------------------------------------------------------- |
| Seller dashboard                                        | 5, whatever the listing, ledger and notification counts |
| Seller earnings                                         | 5, whatever the ledger holds                            |
| Listing activity                                        | 4, whatever the event count                             |
| `payouts:run` for 3 sellers with 2 delivered sales each | 7 — one ledger read, then 2 writes per payout           |

Asserted with `expectsDatabaseQueryCount()` (it counts from the call onward,
so each assertion sits after its fixtures and before the request).

### Numbers

- PHPStan: 2 errors before (the two `countBy()->all()` return docblocks), 0 after.
- Pest: 630 tests / 1440 assertions before, 647 / 1480 after.
- Pint: clean.

### Touched outside the ticket's own files

- `database/factories/SellerFactory.php`, `database/factories/CustomerFactory.php` — `remember_token`, forced by strict models.
- `app/Actions/Fulfillment/MarkShipped.php`, `app/Actions/Fulfillment/ConfirmDelivered.php` — the loads `RollUpOrderStatus` now expects.
- `tests/Pest.php`, `tests/SidecarsTest.php` — new sidecar directories; `app/Models/ListingEvent.php` left the exception list.
- `docs/architecture.md`, `docs/escrow.md` — the ledger fold, the schedule, the strict-model and composer wiring, the Pest bindings, the suite count.

### Left out

- `docs/review.md` names no aggregate, ledger fold, or suite count, so it needed no edit; MAINT-002 owns its numbers.
- `Model::unguard()` was not added — `#[Fillable]` stays the convention, and
  `preventSilentlyDiscardingAttributes` now enforces it.
