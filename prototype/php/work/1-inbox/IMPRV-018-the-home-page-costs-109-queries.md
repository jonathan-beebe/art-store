---
id: IMPRV-018
type: improvement
status: open
created: 2026-08-30
---

# IMPRV-018: The home page costs 109 queries

## Problem

`GET /` reports `data.db = {"queries": 109, "total_ms": 25.45}` on its
`http.request` did line against the seeded catalogue. The page renders
one featured piece, a row of mediums, a row of categories, and twelve
listing cards — a hundred queries is a per-item read the page is paying
for over and over.

Three loops each issue a query per item:

- `CategoryBrowse::forStorefront()`
  (`app/Support/Shop/CategoryBrowse.php:37-52`) runs two queries per root
  category inside `array_map` — a cover lookup with
  `withCount('favorites')` and a separate `count()`.
- `MediumBrowse::forStorefront()`
  (`app/Support/Shop/MediumBrowse.php:22-35`) has the same shape, a cover
  lookup per medium.
- `listing-card.blade.php:9` calls `$listing->imageUrl()`, and
  `StorefrontController:47` eager-loads `seller` but not the images
  relation, so each of the twelve cards reads its own.

The count scales with the catalogue: more categories, more mediums, and
a longer listing set each add queries rather than sharing one.

## Goal

The home page's cost stops growing with the number of things it shows.

## Outcome

`GET /` renders the same page from a number of queries that does not
grow with the count of categories, mediums, or listings on it, and the
`data.db.queries` on its `http.request` did line is bounded by a test
that fails if a future change reintroduces a per-item read. The other
pages that share these helpers — `/medium/{medium}`, `/browse/{path}`,
`/search` — get the same benefit rather than a home-page-only fix.

## Why it matters

This is the store's front door, the page most visitors load first and
the one most likely to be hit by a crawler or a burst. At seed scale it
costs 25ms; the shape means that grows with the catalogue, and the
prototype is where the shape is decided.

## Discovery notes

Advisory:

- `data.db` (IMPRV-017) is how this surfaced and is the natural way to
  assert it: a feature test can read the count off the logged line, or
  `DB::listen` can count directly the way
  `AccountingControllerTest` already does.
- The two browse helpers look like one grouped aggregate each — counts
  by category or medium in a single query, and cover ids in a second —
  rather than a loop. Their existing per-item ordering rules (favorites
  desc, then created_at, then id) need preserving.
- The card images look like a missing `with()` on the listing query, but
  check what `imageUrl()` actually touches before assuming.
- Worth confirming the real figure per page after the change rather than
  trusting the seed count alone; `/browse` and `/search` render the same
  cards.

## Related work

- IMPRV-017 — request lines carry database query count and time (which
  made this visible)
- DSGN-007 — the storefront home as an invitation to explore (which
  added the category covers and the two listing sets)
