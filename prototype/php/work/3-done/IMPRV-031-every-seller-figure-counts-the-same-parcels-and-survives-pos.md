---
id: IMPRV-031
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-031: Every seller figure counts the same parcels and survives Postgres

## Problem
The audit (`__local__/design/seller-portal/AUDIT.md` §2 items 3, 4, 8, 10, 13–19 and §3 item 4) found the paid/live rule written six ways with one copy missing the paid gate (`app/Seller/CustomerOnOrder.php:51-57`), the dashboard earnings tile and the earnings page disagreeing on a parcel declined in a later period, seeded shipped and delivered parcels with no matching events, a partial unique index and negative sentinel positions that Postgres and MySQL reject, a removed store picture left on disk, position reads outside their transactions, an unordered `Order::items()` deciding which flow a parcel ships by, and two "latest per parcel" reads that fetch every row.

## Goal
One statement of which parcels count, read by every seller figure, on a schema that holds on the three databases the alignment contract names.

## Outcome
- `Fulfillment::live()`, `Fulfillment::counted()` (live and on a paid order), and `Order::hasBeenPaid()` are the only places the paid/live rule is written; `SellerCustomers`, `ListingTable`, `ListingActivity`, `SellerOverview`, `CustomerOnOrder`, `CustomerMessageController`, `HeldEscrow`, `EarningsPeriods`, `PeriodSales` read them. A test proves an unpaid order appears in none of the seller figures, including the order page's customer card.
- The dashboard earnings tile and the earnings page compute net by one model (the earnings page's: gross paid sales, refunds netted in the period they land) and a test holds them equal for a parcel declined in a later period; `docs/seller-portal.md` describes that model.
- Every seeded shipped or delivered parcel carries its `shipped` and `delivered` events; a test walks the seed and asserts status and log agree.
- The one-default-flow index is `where is_default` (bare) and steps park above the range; the migration comments and the docs say what each database accepts.
- Removing a store picture removes its file when the store owns it; a seeded picture that shares a listing photo's path is left, and the action says why.
- `AddStoreSection` and `MoveStoreSection` read positions inside the transaction that writes them; `Order::items()` orders by `created_at` then `id` — there is no `position` column on `order_items`; `StoreImageRequest` mints the profile the way `StoreController::show` does; `FulfillmentLanes::notes()` and `SellerCustomers::shippedIdentities()` read latest-per-parcel in one grouped query each; `Order::shippingAddressLines()` sits under its own docblock and drops an empty city cleanly.
- Factories never mint unrelated parents (`StoreImageFactory`, `StoreSectionImageFactory`, `FulfillmentEventFactory` follow `FulfillmentFlowStepFactory`).
- `make precommit` green; `make check` green before the PR.

## Why it matters
A seller who sees two numbers for one buyer stops trusting both. The schema portability items are the difference between a prototype and the contract the other two prototypes implement.

## Discovery notes
- Items 3, 4, 8, 10, 13, 14, 15, 16, 17, 18, 19 of `__local__/design/seller-portal/AUDIT.md` §2; §3 item 4; §4 factories bullet.
- `Order::paidStatuses()` exists; mirror it with `Fulfillment::liveStatuses()`.
- The dashboard's `SellerOverview::parcelsPlacedBetween()` is the side to change (audit §2 item 4).

## Related work
- FEAT-051 (event log), FEAT-054 (customers), FEAT-055 (dashboard), FEAT-060 (earnings)
- docs/alignment.md §1 and §4.5

## Working
- `Fulfillment::liveStatuses()`/`live()`/`counted()` added beside `Order::paidStatuses()`/`hasBeenPaid()`; `counted()` is `whereIn('fulfillments.status', liveStatuses())` plus `whereHas('order', whereIn('status', Order::paidStatuses()))` — a plain `whereIn` inside the closure, not the `hasBeenPaid()` scope, because Larastan cannot resolve an attribute-based scope on a closure's bare `Builder` parameter. `Fulfillment::latestCompletedStep()` is a `hasOne()->latestOfMany('occurred_at')` for the grouped note read.
- All nine named readers converted: `SellerCustomers` (`countedParcels`, and `shippedIdentities` rewritten as a `joinSub` on `max(placed_at)`), `ListingTable`, `ListingActivity` (both via `whereExists(Fulfillment::query()->counted()->whereColumn(...))`), `CustomerOnOrder` (the audited bug — `theirParcels()` had no paid gate), `CustomerMessageController::latestParcel()`. `HeldEscrow`, `EarningsPeriods`, `PeriodSales` already called `Order::paidStatuses()` directly (no hand-rolled duplicate existed) — no diff needed there.
- `SellerOverview::parcelsPlacedBetween()` rewritten to the earnings-page model: gross sales/fee folded by `orders.placed_at` regardless of fulfillment status, refunds folded separately from `ledger_entries` by `occurred_at` and netted against whichever day they land on. Two queries instead of one; the dashboard's fixed-query-count test bumped 35→36.
- `FulfillmentLanes::completedSteps()` uses the new `latestCompletedStep()` eager load; `unansweredQuestions()` and `SellerCustomers::shippedIdentities()` use a `joinSub` on a `max()` subquery (Laravel's `latestOfMany` can't order by a joined table's column, which `shippedIdentities` needs — `orders.placed_at`).
- Migration 000100: partial index dropped `= 1`, reads `where is_default` bare (SQLite and Postgres both accept it as a boolean predicate; MySQL has no partial index at all, noted in the comment). Migration 000101: comment updated, no schema change — the column stays `unsignedInteger` since `SaveFulfillmentFlow` no longer writes negative values.
- `SaveFulfillmentFlow::PARKED_POSITION` changed from `-1` (decrementing) to `9999` (incrementing), matching `MoveStoreSection::SENTINEL_POSITION`'s idiom.
- `FulfillmentFlowSeeder` backfills `shipped` and `delivered` transition events (in addition to the existing label `step_completed` backfill) for every fulfillment with `shipped_at`/`delivered_at` set, using literal `FulfillmentEventKind::Shipped`/`::Delivered` rather than `forStatus($fulfillment->status)` — a fulfillment later refunded still needs its historical shipped/delivered events even though its current status is `refunded`.
- **Scope change mid-build, from the coordinator, replacing DECISIONS.md §2.10's original call**: `StoreProfileSeeder` now copies each listing photo onto the store's own `stores/` path (`Storage::disk('public')->copy()`) instead of pointing `store_images.path` at the listing's file; `RemoveStoreImage` deletes the file unconditionally, no shared-path guard. (My first pass had implemented the original DECISIONS.md call — delete only when the path is under `stores/`, leave a `listings/`-prefixed path alone — before this instruction arrived; that version is fully superseded.)
- `AddStoreSection` locks the `StoreProfile` row (`lockForUpdate()`) before reading `max(position)`, inside the transaction — not `lockForUpdate()` on the aggregate query itself, since Postgres refuses `FOR UPDATE` combined with an aggregate function. `MoveStoreSection` locks the section row and the sibling set before reading positions.
- `StoreImageRequest::storeProfile()` calls `app(StartStore::class)($seller)` instead of `$seller->storeProfile()->firstOrFail()`.
- `Order::items()` orders by `created_at` then `id` (the house tie-break idiom, matching `FulfillmentLanes::query()`). `shippingAddressLines()` moved under its own docblock (was sitting under `placementPlan()`'s) and no longer builds a leading `", REGION POSTAL"` when the city is blank.
- `StoreImageFactory`, `StoreSectionImageFactory`, `FulfillmentEventFactory` each derive their seller/store from the parent they were handed (a lookup closure keyed off the sibling attribute), matching `FulfillmentFlowStepFactory`'s existing pattern.
- Tests added: `app/Seller/PaidGateTest.php` (one dataset test spanning six readers), `SellerOverviewTest` (unpaid-order exclusion + the cross-period-decline equality test against `EarningsPeriods`), `FulfillmentFlowSeederTest` (migration-SQL assertion + shipped/delivered seed walk), `SaveFulfillmentFlowTest` (query-binding assertion that no position written during a reorder is negative), `RemoveStoreImageTest` + `StoreProfileSeederTest` (file-copy/delete behavior for the mid-build scope change), `OrderTest` (items ordering, shippingAddressLines including the empty-city case), `FulfillmentTest` (two-listing flow-naming determinism), `StoreImageRequestTest` (first-POST case), `SellerCustomersTest` (query-count guard for `shippedIdentities`), and one factory-relatedness test each for the three fixed factories.
- Nothing scaled back from the outcome bullets; `HeldEscrow`/`EarningsPeriods`/`PeriodSales` needed no code change since they already called the canonical `Order::paidStatuses()`.
- Gate: `make check` green — 5198 tests passed (35814 assertions), 99.5% line coverage, Pint and PHPStan clean.

## Review pass
Rebased onto `php/seller-portal-next` (its journal counters and entries kept, IMPRV-031's two lines appended by timestamp; its own rewrite of `orders.md`/`seller-portal.md`/`data-model.md` re-carried my earnings-model and store-picture paragraphs by hand at the conflicts). Twelve findings, fixed in twelve commits:

1. `Fulfillment::latestCompletedStep()`: `where('kind')` chained before `latestOfMany()` constrained the outer query only, not the `MAX(occurred_at)` subquery — a later `shipped` event on the same parcel won the grouping and the step-completed row never surfaced. Now `hasOne(...)->ofMany(['occurred_at' => 'max'], fn ($q) => $q->where('kind', StepCompleted))`, the documented way to add a constraint alongside one-of-many. New lane test: a step completed, then the parcel shipped, still shows the step's note.
2. `RangeChange::between()` divided by a signed `$previous`; a negative previous (now reachable since `SellerOverview`'s net can go negative) flipped the sign, so a recovery read as a further loss. Divides by `abs($previous)`. New dataset case (`-100` vs `-300` reads `+66.7%` up) and a `SellerOverview` case: a range holding only a refund (the sale a period earlier) reads `-$90.00`, not a wrongly-signed positive.
3. `MoveStoreSection` locked only the section and its neighbor; `AddStoreSection` locked the profile row — the two writers could each write the `9999` sentinel without excluding each other. `MoveStoreSection` now takes the same `StoreProfile ... lockForUpdate()->sole()` first. Docblock corrected: `MAX_PER_PROFILE` caps how many sections exist, not the position values, which climb with every section ever added.
4. `Fulfillment::live()` had no caller outside `counted()`, which repeated its clause by hand. Added `onPaidOrder()`; `counted()` is now `live()->onPaidOrder()`. `HeldEscrow`, `EarningsPeriods`, `PeriodSales`, and `SellerOverview::parcelsPlacedBetween()` read `onPaidOrder()` alone — each keeps a declined or refunded parcel, which `live()` would drop.
5. `orders.md`'s own copy of the flow section still claimed `= 1` and negative parking (the rebase brought back the pre-fix text); corrected the same way `seller-portal.md` already reads. `seller-portal.md`'s own `= 1` line turned out to already be gone in the rebased doc — nothing to fix there.
6. The seed-walk test asserted existence only. Now asserts `occurred_at` equals `shipped_at`/`delivered_at` and the actor type. That caught a real bug the existence check missed: `FulfillmentFlowSeeder`'s backfill duplicated a `shipped`/`delivered` event on any parcel `OrderHistorySeeder` had already carried through the real `MarkShipped`/`ConfirmDelivered` action, since the events table's unique index counts each null step id as its own value and never caught the duplicate. Guarded on an existing event first.
7. Folded into 4.
8. `StoreProfileSeeder` copied to `stores/{basename}` — two listings sharing a filename would have collided two stores onto one file, and `RemoveStoreImage`'s unconditional delete would have taken the other store's picture down with it. Path is now `stores/{profile id}-{basename}`. New test forces two sellers' listing photos to share a name and asserts the two stores' picture-path sets never intersect.
9. `StoreSectionImageFactory::imageOf()` called `StoreImage::factory()->create()` inside `definition()`'s own resolution — hit the database eagerly and bypassed whatever a caller overrode. Now `StoreImage::factory()->state([...])` on both branches, returning a lazy `Factory<StoreImage>` always. All four `sellerOf()`/`imageOf()` helpers across the three factories I touched, plus the pre-existing `FulfillmentFlowStepFactory::sellerOf()` for consistency, now carry a typed return (`string|Factory<Seller>`, or `Factory<StoreImage>` for `imageOf()`), `mixed` only on the parameter.
10. Replaced the seeder test's migration-file text-content assertion (tautological — proves nothing about actual database behavior) with a schema-level one: `FulfillmentFlowTest.php` now queries `sqlite_master` for the index's own SQL and asserts it. The "second default flow throws" functional guard the review offered as the other option already existed in that same file, so no new test duplicates it. Tightened `SaveFulfillmentFlowTest`'s reorder-binding guard from `>= 0` to `>= 9999` for the parked half specifically (the final 0-based writes are asserted `[0, 1]` in the same test), distinguishing the two phases by binding order.
11. Ticket's outcome bullet corrected: `Order::items()` orders by `created_at` then `id` — there is no `position` column on `order_items`.
12. Seven commit bodies containing "instead of" reworded via `git filter-branch --msg-filter` scoped to `php/seller-portal-next..HEAD` (trees verified identical before/after via `git diff --stat`, backup ref removed after). Six comment/test-name locations reworded to state the positive fact: `Fulfillment.php:118` (the `ofMany` docblock, folded into item 1's commit), `:154` ("still live" instead of "not declined, not refunded"), `Order.php:124` ("drops straight to" instead of "rather than a leading comma"), `FulfillmentLanes.php:118` ("the buyer's unanswered question" instead of "nobody has answered"), `FulfillmentTest.php:129` and `OrderTest.php:154` (test name and comment, "rather than" removed).

Not addressed, out of the review's explicit list: `docs/ontology.md:463` and `docs/data-model.md:496` also still claim `is_default = 1` — spotted while checking item 5, left alone since neither file was named.

Gate: `make check` green — 5202 tests passed (35821 assertions), 99.5% line coverage, Pint and PHPStan clean, 168.32s.
