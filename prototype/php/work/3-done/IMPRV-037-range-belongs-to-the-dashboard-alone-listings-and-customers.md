---
id: IMPRV-037
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-037: Range belongs to the dashboard alone; listings and customers are evergreen

## Problem
`ListingsQueryRequest` and `CustomersQueryRequest` validate and round-trip a `range` parameter that no control on either page can set; the dashboard's links carry it into both, and the listings table's analytics columns and the customers segments read it. The owner's decision ("customers and listings are evergreen resources; the range limits reporting to a timeframe") means the parameter has no place on those two pages.

## Goal
The range is a reporting control that lives on the dashboard and nowhere else.

## Outcome
- `range` is gone from the listings and customers query vocabularies, their requests, their chrome links, and the dashboard's outbound links to them; an explicit `?range=` on those routes is ignored like any unknown parameter the app does not validate (document the choice), or answers 400 if the request already treats unknown keys that way — pick the idiom the requests use today and say so.
- The listings table's analytics columns read a fixed window named in their headers ("last 30 days"); the customers "New this period" segment reads the same fixed window and its footnote says so.
- The dashboard keeps its 7/30/90 control and its own links carry `range` back to itself alone.
- `make precommit` green; `make check` green before the PR.

## Why it matters
A parameter with no control is a trap for the next reader and a silent way for two pages to disagree.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 3. Do this after IMPRV-032 merges (it owns the query requests).

## Related work
- IMPRV-032, FEAT-054, FEAT-055, FEAT-056

## Working
`SellerQueryRequest` no longer owns `range`/`rangeDays()`; `DashboardQueryRequest` keeps them, since the dashboard is the only page `range` still controls. `ListingsQueryRequest` and `CustomersQueryRequest` drop `range` from `rules()` and `roundTripped()`. A `FormRequest` validates only the keys `rules()` names, so a stray `?range=` on either route now falls through unvalidated like any other key the vocabulary does not know — the idiom the requests already use for an unlisted key, generalized rather than replaced; picked over a bare-400 idiom because there was already a working default to reach for. `ListingController` and `CustomerController` each read a fixed thirty-day window off a `private const` instead of the request, so `?range=` has no effect at all, not merely an unvalidated one.

Listings: `ListingSortColumn::label()`'s Views/Favorites/Cart adds now read "… (last 30 days)"; the detail page's own "Views, last N days" wording needed no change, already parameterized by the (now fixed) `rangeDays` the controller passes. Every link that carried `range` (the table/grid row links to their own detail, the dashboard's "All listings" link, `ListingActivity`'s dashboard-row link to a listing) drops it.

Customers: `CustomerSegment::apply()` and `CustomerTally::of()` keep their existing `DateTimeImmutable $rangeStart` parameter unchanged — only the caller changes what it passes (a fixed thirty days, not the request's range), so neither class needed touching. The footnote ("New counts a first order inside the last N days") already read a variable, so only its value changed. The dashboard's Customers tile link drops `range` entirely, per the brief: the dashboard's own links carry `range` back to itself alone, not to earnings, listings, or customers.

`ListingsChromeTest`'s two cases that stood `range` in as a generic round-tripped key now use `sort`/`view`, real vocabulary, so the test reads true after the change.

Tests: `ListingsQueryRequestTest`/`CustomersQueryRequestTest`'s "accepts every documented range" / "400 outside the documented set" pairs became one "ignores a range, whatever value it carries" case each (the old accepted values, the old 400 value, and a bogus string, all 200 now). `ListingControllerTest` replaces the range-narrows-the-table test with one proving the fixed thirty-day window survives a stray `?range=7`, and adds a case for the dropped detail-row-link parameter. `DashboardControllerTest` updates the two range-in-link assertions (the customers tile link, the listing-activity row link). `CustomerControllerTest` adds a stray-range case and a footnote-wording case.

`make precommit`: green (see IMPRV-038's Working section for the shared run covering both tickets — they landed in this worktree together).
