---
id: DSGN-004
type: design
status: open
created: 2026-08-30
---

# DSGN-004: Log viewer redesign: workflow-first filters and columnar rows

## Problem

`/admin/logs` renders its fifteen filter controls as one flat box above the
list (`resources/views/admin/logs/index.blade.php`), every control at the
same visual weight, inside the admin layout's `max-w-6xl` column. The
founder's actual workflow — land on recent shop traffic grouped by request,
scan for trouble, narrow by tapping an id — is buried: domain/level/event
sit beside rarely-typed text inputs, level tallies live in a separate tile
strip, rows are free-flowing inline spans whose columns never align, and a
duration reads the same at 30 ms and 900 ms.

## Goal

The log viewer opens on the traffic a founder came to read and makes
trouble scannable at a glance, with everything secondary one disclosure
away.

## Outcome

Landing on `/admin/logs` shows the most recent grouped requests for
`domain=shop` with health checks hidden. Domain, level, and event are the
visible controls — the level tallies are the level filter — and every
other filter sits behind a collapsed disclosure, with applied filters
shown as removable chips. The list fills the screen; rows are columnar and
aligned (time, request, status, duration, lines, actor, session, story
chevron) with tabular numerals throughout. Duration values are tinted by
threshold: green at ≤ 300 ms, orange at 301–600 ms, red above 600 ms. A
row's ids filter in place, its details (the request's lines, data, error)
open one click away in place, and the story view carries the same header
facts, chips, and columnar line layout.

## Why it matters

The logs page is the founder's first stop when something is wrong; today
the signal (severity, slowness, whose session) hides in an even gray wall,
and reaching the common filters costs typing into a box of controls.
Surfacing scannable severity and speed cues and making id-tap filtering
primary shortens every debugging session.

## Discovery notes

Approved design canvas (three artboards: default view, row expanded in
place, request story):
https://claude.ai/code/artifact/9c9eb2d4-6c67-42f4-8ebd-ec5a650a6db7
The canvas matches the admin site's existing gray Tailwind vocabulary and
severity tints; treat it as the reference, not a pixel spec. Open display
question flagged on the canvas: row id chips there show truncated ids
(prefix + first segment) for scannability with full ids in the expanded
panel and story — confirm or reject at implementation. The filter-link,
actor-control, chevron, and viewer/health default-hide behaviors it draws
already exist (docs/logging.md § "Viewer"); this ticket is layout and
hierarchy over them, JS-off throughout.

## Related work

- FEAT-033 — log store and admin log viewer
- docs/logging.md § "Viewer" — filter links, actor control, chevron,
  health and viewer default-hide semantics (shipped, uncommitted at
  writing)
- docs/alignment.md §5 — `/admin/logs` row and decisions bullets
