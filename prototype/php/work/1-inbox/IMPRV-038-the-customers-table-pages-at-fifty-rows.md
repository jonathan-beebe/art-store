---
id: IMPRV-038
type: improvement
status: open
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
