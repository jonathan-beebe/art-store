---
id: IMPRV-031
type: improvement
status: open
created: 2026-08-26
---

# IMPRV-031: domain filter and group-by-request for the admin log viewer

## Problem

`/admin/logs` (`app/sites/admin/routes/logs.ts`, `queries/log-rows.ts`)
filters on stored line fields only. (1) Nothing narrows the list to one of
the three sites — the buyer storefront, `/seller`, `/admin` — so all three
interleave, and a line's site is derivable only through its request's path
(the `http.request` will line's `data.path`); lines store no site field.
(2) The list renders one row per line, so a request's 2–30-line story
arrives interleaved with every other request's, and scanning "what
happened" per request means opening story views one at a time.

## Goal

The list reads per-site and per-request the way a founder debugs.

## Outcome

(1) A "Domain" filter, placed before Level, whose values are the three
sites; selecting one shows only lines belonging to that site's requests,
empty means all, an unrecognised value answers 400. (2) A "group logs by
request" checkbox in the filter form; checked, the list shows one row per
`request_id`, newest first, summarizing what the request did — the way
Gmail collapses a thread — and each group opens into that request's lines.
Both compose with the existing filters and survive the pager round-trip.

## Why it matters

Three sites' traffic interleaved at one row per line is the page's biggest
scan cost, and it is the page the founders refresh while debugging.

## Discovery notes

Domain derivation is the design decision: prefix-match the request's
will-line path (`/admin*`, `/seller*`, else buyer) via a join/subquery on
`request_id`, or stamp a derived column at ingest — maker's call; note the
buyer bucket catches `/health` and `/events` unless excluded, and CLI
lines have no `request_id` at all (decide whether a domain selection shows
them). Grouping changes count/paging semantics — a page counts groups;
decide the group row's summary facts (the root will/did pair carries
method, path, status, `duration_ms`) and where a group opens (inline lines
vs the existing `/admin/logs/requests/:requestId` story view, which
already renders one request). Lines with no `request_id`, grouped:
`txn_id` or singly — decide.

## Related work

- FEAT-021 (e74d9d9, 0ab2f5f, fb5d9f3) — the log store and viewer
- BUG-010 (322a7a8) — the viewer's filter round-trips surfaced it
