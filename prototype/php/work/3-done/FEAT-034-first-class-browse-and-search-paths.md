---
id: FEAT-034
type: feature
status: resolved
created: 2026-08-29
---

# FEAT-034: First-class browse and search paths

## Problem
Storefront browsing and search rode the home page's query params (`/?medium=ceramic&q=cup`). The medium tiles' link builder carried the current `q` into every tap, so a search followed by a category tap left a composed URL that narrowed to unrelated result sets. Filtered views were unshareable as paths, and (before IMPRV-016) invisible in the logs.

## Goal
One dimension, one path prefix — a scheme future dimensions (tags) copy:
`/medium/{medium}`, `/browse/{categoryPath}` (one or two slug segments over the category tree's materialized path), `/search?q=`. Search replaces browse; the stale-`q` composition becomes unrepresentable.

## Outcome
- [x] `/medium/{medium}` — Medium-attribute browse on the derived `mb_strtolower(label)` value; unknown value 404s.
- [x] `/browse/{categoryPath}` — new category browse page matched against `categories.path` (`/browse/jewelry`, `/browse/jewelry/rings`); lists the category and its descendants' for-sale listings, links browsable children; unknown path or `browsable = false` 404s — the first read-side consumer of `browsable`.
- [x] `/search?q=` — search results page; the layout-wide header form targets it; a blank `q` renders a prompt rather than an empty result set. Every search logs via IMPRV-016's `data.query`.
- [x] `/` drops `q`/`medium`; legacy URLs redirect — `/?q=` → `/search?q=`, `/?medium=` → `/medium/{medium}`, both → `/search?q=` with the medium dropped.
- [x] The `$withTerm` closure is deleted from all three browse partials; medium tiles link bare paths, home gains a category row, the listing grid/pager/empty state extracted into one shared partial, each new page carries its own title.
- [x] `make check` green; coverage 100%; 2,989 tests; live-verified (routes, 404s, redirects, link hygiene, search line in the log store).
- [x] `docs/alignment.md` reconciliation log records the scheme; Node and Rails owe it when their storefronts grow the equivalent surfaces.

## Why it matters
Category, medium, and search states are now shareable URLs a founder can read in the logs, and the nonsensical filter+search composition is gone structurally rather than guarded against.
