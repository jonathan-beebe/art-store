---
id: DSGN-004
type: design
status: resolved
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

## Working

2026-08-30 — Implemented. The open display question resolved as: full ids
in the expanded panel and the story view, truncated (prefix + first 8 body
characters) only on a collapsed row's own chip
(`App\Logging\Admin\LogIdLinks::truncate()`); `components/admin/log-actor.blade.php`
and the new `components/admin/log-id-chip.blade.php` both grew a
`:truncate` prop rather than a second component, so the truncation is one
code path shared with the existing filter-link/actor-control behaviors
(`LogFilterLinks`, `log-actor`, `log-ids`) — reused as they stood, not
reimplemented.

`GET /admin/logs` with no query string redirects to
`?domain=shop&group=1` (`LogController::index`, `$request->query() ===
[]`); any parameter present, even empty, skips it — filter semantics
unchanged. Domain becomes a segmented control, the four level tallies
become the level filter's own clickable chips (`aria-current="true"` on
the active one), and everything else — phase, request/txn/session/actor/
msg/from/to/key/value, health, viewer — moved into a `<details>` "More
filters" disclosure with an active-filter dot; three hidden inputs
(domain/level/group) inside the one GET form keep a More-filters submit
from dropping the segmented controls' state, since a GET form replaces the
query string with only its own fields. An applied-state strip shows every
active filter as a removable chip plus the health/viewer quiet toggles and
a result count.

Duration gets a server-selected tint — `App\Logging\Admin\LogDurationTint`
(green ≤300ms / orange 301–600ms / red >600ms), its own sidecar test.
`App\Logging\Admin\LogTimestamp::timeOfDay()` slices `HH:MM:SS.mmm` for
every row's time cell, full ISO in `title`. The Requests view is a
columnar grid of native `<details>`/`<summary>` rows (not a literal
`<table>` — grouping and in-place expansion do not fit one); the Lines
view keeps its `<table>`, restyled to match. `App\Logging\Admin\LogStoryHeader` grew
`method`/`path`/`status`/`txnId` (reading the root `http.request` pair the
same way `LogRowQuery::summarizeRequestGroup` already did — the shared
JSON-field reader extracted to `App\Logging\Admin\LogRequestData` so
neither duplicates the other) so a Requests row's actor/session/txn chips
and the story header's tinted card both build off one read-model. The
logs pages render `x-layouts.admin :full-width="true"`, a new opt-out prop
on the shell; every other admin page keeps `max-w-6xl`.

Landed on a worktree that started from `main` (pre-dating `chore/docs`'s
filter-link/actor-control/viewer-filter/`/up`-probe work and IMPRV-017's
`data.db` field) — merged `chore/docs` twice (once per its own further
commits) rather than reimplementing what it had already shipped; both
merges auto-resolved except `journal.md` and a handful of `/admin/logs`
test calls whose bare (no-query) form now hits the new redirect, fixed by
appending `?domain=` to keep their original "no filter" intent.

Test updates beyond the merge: `LogControllerTest`'s level-`<select>`
assertion became an `aria-current` regex match (level is a chip now); the
grouped-row duration/line-count assertions swapped their `<dd>`/"N lines"
text pattern for the new inline `data-stat`/`data-cell` markup shape (no
behavior change, only where the digit sits in the DOM); two new tests
cover the redirect and its "any query parameter" bypass. make check green
(lint → assets → coverage), coverage 100%, 3041 tests.

2026-08-30 — Review fix: the Requests view had grown an ARIA-table overlay
(`role="table"`/`role="row"`/`role="columnheader"`/`role="cell"`) meant as
a grid-semantics stand-in for the missing `<table>`. Reviewer caught it as
both invalid and harmful — `role="row"` on `<summary>` overrides the
element's own native disclosure semantics (screen readers lose the
expand/collapse affordance, WCAG 4.1.2), and the structure fails ARIA
table requirements regardless (the role-less `<details>` sits between
table and row; the expanded panel is an illegal owned child of a table
row). Removed every one of those roles — the wrapper, the visual
column-header strip, `<summary>`, and the cell spans — keeping the grid
classes and every `data-*` hook as they were. The column-header strip is
now `aria-hidden="true"` (a sighted-only visual aid; its sr-only "Open"
label went with it, since the chevron's own `aria-label` already names
the action) and the page's "Logs" `<h1>` is the list's accessible
context — no replacement heading needed. `role="group"` on the
domain/level/view segmented controls was untouched (valid there). No test
asserted the removed roles, so none needed updating. make test, make
lint, make check all green afterward; coverage 100%, 3041 tests
unchanged.
