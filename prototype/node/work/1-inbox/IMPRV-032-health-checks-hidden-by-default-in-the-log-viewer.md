---
id: IMPRV-032
type: improvement
status: open
created: 2026-08-26
---

# IMPRV-032: health checks hidden by default in the log viewer

## Problem

The container healthcheck polls `GET /health` every ~10 seconds and each
poll writes a will/did pair to the log store, so the default `/admin/logs`
list buries real traffic under health noise (dev store measured
2026-08-26: 979 of 1,045 lines are `http.request`, the steady majority of
them `/health` polls).

## Goal

The default list shows the traffic a founder came to read.

## Outcome

Health-check lines are hidden from `/admin/logs` by default; a visible
filter control includes them again, and its state round-trips through the
form and pager like every other filter.

## Why it matters

Two noise lines every ten seconds outnumber the story lines between
refreshes on the page whose whole job is showing the story.

## Discovery notes

"Health check" means requests whose path is `/health` — hiding should
cover the pair and any line sharing those request_ids. The level
tiles/tallies should respect the same default so counts match the visible
list. Interacts with IMPRV-031's buyer-domain bucket if both land —
whichever lands second reconciles. The story view stays unfiltered (a
health request's story is still addressable by id).

## Related work

- FEAT-021 (e74d9d9, 0ab2f5f, fb5d9f3) — the log store and viewer
