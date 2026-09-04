---
id: IMPRV-037
type: improvement
status: open
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
- The dashboard keeps its 7/30/90 control and its own links carry `range` only to the earnings page and back to itself.
- `make precommit` green; `make check` green before the PR.

## Why it matters
A parameter with no control is a trap for the next reader and a silent way for two pages to disagree.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 3. Do this after IMPRV-032 merges (it owns the query requests).

## Related work
- IMPRV-032, FEAT-054, FEAT-055, FEAT-056
