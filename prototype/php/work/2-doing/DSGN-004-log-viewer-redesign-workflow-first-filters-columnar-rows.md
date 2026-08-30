---
id: DSGN-004
type: design
status: inbox
created: 2026-08-30
---

# DSGN-004: Log viewer redesign — workflow-first filters, columnar rows

## Problem
`/admin/logs` (FEAT-033) ships every filter the log store supports, but
lays them out as one long form of equal-weight selects and text inputs,
followed by four separate stat tiles that duplicate the level filter, then
either a flat table or `<details>`-per-request rows that repeat the same
handful of columns as inline spans. Nothing distinguishes the three filters
a founder reaches for constantly (domain, level, event) from the eight they
reach for rarely (txn/session/actor/msg/from/to/key/value), and the row
layout has no consistent columnar rhythm across the list, the expanded
request panel, and the story view.

## Goal
The same query and filter semantics FEAT-033 shipped, restated as a
workflow-first layout: primary controls up front, everything else tucked
behind a disclosure, and one columnar row anatomy reused across the grouped
list, its in-place expansion, and the per-request story.

## Outcome
- A `/design` canvas (Main, RowExpanded, Story artboards) is the approved
  reference, human-reviewed before implementation.
- `GET /admin/logs` with no query parameters redirects to
  `/admin/logs?domain=shop&group=1`; any query parameter present (even
  empty) skips the redirect. Filter semantics themselves are unchanged —
  empty still means all, per `docs/alignment.md` §5.
- Header bar: title; a domain segmented control (All/shop/seller/admin);
  the four level tallies as clickable chips that ARE the level filter
  (replacing the separate stat-tile strip); an event select; a
  Requests/Lines view toggle; a More-filters disclosure holding every
  remaining filter input (request/txn/session/actor/msg/from/to/key/value,
  and the health checkbox) with an indicator when a hidden filter is
  active. One GET form still submits every field.
- Applied-state strip: active filters as removable chips, a quiet "health
  checks hidden · show" affordance, and the result count.
- Requests (grouped) view: columnar grid rows — time, method+path, status,
  a tinted duration, line count, actor chip, session chip, chevron —
  expanding in place via `<details>` to an id rail (Filter by
  req/txn/ses/cus, plus a labeled View customer/seller control) and the
  request's lines in the same columnar line layout.
- Lines (ungrouped) view: the same columnar treatment inside a `<table>`.
- Story view: breadcrumb back to Logs, a worst-severity-tinted header card
  (method/path/status/tinted duration/line span), the same id rail, and
  columnar lines with data/error disclosures.
- Row-level id chips (actor/session, in a collapsed row) show a truncated
  id — prefix plus the first 8 id characters, e.g. `cus_01J5X3M9` — with
  the link href, `title`, and accessible name carrying the full id.
  Expanded panels and the story view show full ids throughout.
- Duration gets a server-selected tint: green ≤300ms, orange 301–600ms, red
  >600ms, encoded in one value object with its own sidecar test — not
  scattered Blade conditionals.
- The logs pages (list and story) escape the admin layout's `max-w-6xl`
  container; every other admin page keeps its current width.
- JS-off holds throughout: `<details>`/`<summary>` for every expansion,
  the More-filters disclosure is a `<details>`, every control is a link or
  a plain form field, no scripts.
- Every shipped behavior survives: filter-link ids carrying the current
  filter set, the health-hidden default, severity tints, level tallies
  excluding hidden traffic, 400 on an unrecognised filter value, and pager
  round-tripping.

## Why it matters
FEAT-033 proved the log store and the filter set; this pass makes the
viewer itself usable at a glance — a founder should be able to land on
`/admin/logs`, read the level tallies, and open a failing request's story
without first parsing a wall of form fields.

## Related work
- prototype/php/work/3-done/FEAT-033-log-store-and-admin-log-viewer.md
- docs/logging.md § "Viewer" (contract this design restates, not changes)
- prototype/php/docs/log-store.md § "Viewer" (updated by this ticket)

## Working
