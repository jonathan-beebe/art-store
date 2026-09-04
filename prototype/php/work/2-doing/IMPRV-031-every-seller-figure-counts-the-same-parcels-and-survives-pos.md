---
id: IMPRV-031
type: improvement
status: in-progress
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
- `AddStoreSection` and `MoveStoreSection` read positions inside the transaction that writes them; `Order::items()` orders by position then id; `StoreImageRequest` mints the profile the way `StoreController::show` does; `FulfillmentLanes::notes()` and `SellerCustomers::shippedIdentities()` read latest-per-parcel in one grouped query each; `Order::shippingAddressLines()` sits under its own docblock and drops an empty city cleanly.
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
