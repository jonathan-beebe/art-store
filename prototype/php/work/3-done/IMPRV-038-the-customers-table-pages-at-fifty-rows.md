---
id: IMPRV-038
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-038: The customers table pages at fifty rows

## Problem
`/seller/customers` lists every buyer in one table with no paging (FEAT-054 left it out). A seller with hundreds of buyers gets one long page and one long query.

## Goal
A seller with any number of buyers can read the customers table a page at a time.

## Outcome
- The customers table shows fifty rows per page with a pager in the seller chrome (the `App\Support\Page` + pager idiom the admin tables use, restyled to the seller's indigo accent), the pager carrying the current segment and sort.
- The tiles above the table still count every buyer; the sort applies across pages (sorting happens in the query, and the grouped aggregate query is paged in SQL).
- Page `0`, a page past the end, and a non-integer page answer 400 through the query request.
- `make precommit` green; `make check` green before the PR.

## Why it matters
The customers list is the one seller table whose size grows without bound.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 4 (pager, fifty). `x-admin.pager` exists; the seller side wants `x-seller.pager` or a shared one. Do this after IMPRV-032 merges.

## Related work
- FEAT-054, IMPRV-032

## Working
Chose `x-seller.pager` (a new component) over promoting `x-admin.pager` to a shared `x-pager`: the admin component's three call sites (logs, actors, channels) sit outside this ticket, and touching them would widen the diff into files other concurrent lanes might also touch. `x-seller.pager` mirrors `x-admin.pager`'s arithmetic (`App\Support\Page`) and shape (Previous / Page N of M / Next, hidden at one page), styled with the seller chrome's link and muted-text tokens, and builds its hrefs with `route()` off a `routeName` + `query` array rather than a hand-built query string — the same idiom `ColumnHeaders`/`NavLinks` already use for every other seller-table link.

`CustomersQueryRequest` validates `page` as `nullable|integer|min:1` (`0` and a non-integer 400 through the existing `failedValidation()` path) and adds its own `page(int $totalCount): Page` that refuses a page past the end — deliberately not the admin idiom: `AnalyticsActorsQueryRequest`'s own docblock states a value past the end is `Page::of()`'s own concern to clamp, not the request's to refuse. This ticket asks for the stricter rule, so `page()` compares `Page::of()`'s clamped number against what was actually asked for and 400s on a mismatch.

`SellerCustomers` gains `countForSegment()` and `pageForSeller()` alongside the existing unpaged `forSeller()`/`forCustomer()`, kept as-is — the tiles above the table still read `forSeller()` unfiltered, so they count every buyer whatever the segment or page shows. Both new methods share `segmentedQuery()`, the same grouped aggregate `countedParcels()` already built, narrowed by a `HAVING` clause per segment (`count(*) >= 2` for Repeat, `min(placed_at) >= ?` for New — `CustomerRow::REPEAT_ORDERS` made `public const` so the SQL and the PHP `isRepeatBuyer()` read the same number). `pageForSeller()` adds favorites/conversations (two correlated `selectSub()` counts) and identity (the account's own `customers.name`/`email` alongside a correlated "latest counted parcel's order" subquery for each, `Fulfillment::liveStatuses()`/`Order::paidStatuses()` reused so the fallback reads the same live-and-paid pair `countedParcels()` filters by) to the same query, then `ORDER BY`/`LIMIT`/`OFFSET` — one query for a page, whatever page. `countForSegment()` wraps the same `HAVING`-narrowed query, columns trimmed to the group key, in a `count(*)` subquery.

First attempt built the name/email fallback as one hand-written `COALESCE(...)` SQL string with `IN (?,?,...)` placeholders, passed to `selectRaw()`. PHPStan (level max) rejects that: `selectRaw()`'s (and `DB::raw()`'s) argument must be a `literal-string`, and a string assembled from `implode()`/interpolated variables is not one, whatever its actual safety. Rewrote it as two `selectSub()` correlated subqueries (`shipped_name`, `shipped_email`, built with `whereIn()`/`whereColumn()`, no raw SQL at all) alongside the account's own two columns, and moved the "account wins, shipped falls back" choice into `toRowFromQuery()` — the COALESCE happens in PHP instead of SQL. Cleaner than the raw-string version regardless of the PHPStan rule, and avoids duplicating the live/paid predicate as hand-typed status strings.

Sort maps each `CustomerSortColumn` case to its SQL column/alias (`lower(name)` for Name, matching the PHP path's `mb_strtolower`); the id tie-break stays ascending under both directions (DECISIONS.md 3.1), expressed as a trailing `ORDER BY fulfillments.customer_id ASC`.

`CustomerController::index()` computes `countForSegment()`, builds the `Page` through the request (400 on a bad one), and reads `pageForSeller()` for the rows the view renders — `RowSort`/PHP-side sorting are gone from this route entirely. Tiles keep reading the old unpaged `forSeller()`.

Tests: `SellerCustomersTest` gets seven new cases (sort+page in SQL, tie-break both directions, segment count matches segment page, the New window, identity/favorites/conversations resolved correctly on a page, and a fixed-query-count guard mirroring the existing unpaged one — six pass through the query builder, one guards the query count). `CustomersQueryRequestTest` covers page 1 with nothing to show, `0`, three non-integer shapes, past-the-end, and the exact last page. `CustomerControllerTest` covers the fifty-row page split, a sort surviving onto page two, the tiles still counting every buyer on page two, the pager appearing past fifty and not under it, the pager's Next link carrying segment/sort/dir, and the 400.

`make precommit`: green, 5262 tests (see the handover report for the tail).
