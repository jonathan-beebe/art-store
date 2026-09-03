---
id: DSGN-009
type: design
status: resolved
created: 2026-09-02
---

# DSGN-009: the funnel draws as a funnel

## Problem

The funnel FEAT-046 ships
(resources/views/components/admin/analytics/funnel.blade.php) is a row of
seven stat tiles. On the dev stack with one visitor and ten listing views
it reads "1000% of visitors", the share bar under "Listing views" runs
past its tile and across the next three, every tile repeats "new vs
previous range" as three lines of copy, no drop-off between steps is
drawn or named, and at 390px the tiles wrap into a grid that no longer
reads left to right. Two causes sit under the rendering: the steps count
different units (visitors are distinct sessions, every later step is an
event count), so a step can exceed the one before it and a bar can exceed
its container; and the component composes tiles that were designed for
independent numbers, with a funnel implied by a bar underneath.

## Goal

An admin reads the funnel at a glance: which step loses the most, how the
range compares to the one before, on any screen.

## Outcome

A design canvas (the `/design` skill, matched to the admin chrome from
the source) shows the funnel at desktop and phone widths, in light and
dark, for the store range and for a single listing, with real numbers
from the seeded data; the design names the pattern it follows and the
Tailwind UI pieces it composes; the funnel counts one unit at every step
so no step exceeds the one before it and every bar fits its container;
the drop-off between adjacent steps is drawn and labelled; the previous
range is one glyph or overlay per step, never a sentence; the component
takes an ordered list of step values (key, label, count, previous, rate
against the prerequisite step) and knows how to draw any number of
steps, so the same component serves the store, a listing, a seller, and
a future channel funnel; an architecture note (`/architecture`, a
mermaid diagram in `docs/analytics.md` or a sibling) fixes the boundary
between the query that produces steps and the component that draws
them; the design is accepted by the human before an implementation
ticket is filed.

## Why it matters

The funnel is the number a marketer opens first. A row of tiles that
overflows and says "1000% of visitors" undermines every other number on
the page, and a funnel whose steps count different things cannot be
compared step to step.

## Discovery notes

- Unit: standard funnel tools count unique people per step (sessions or
  actors who reached it), so each step is a subset of the last and the
  rate is a share. `App\Analytics\Admin\Funnel` already carries
  `session_id` on every event; counting distinct sessions per name is the
  same query shape with `count(distinct session_id)`. Visitors would then
  be sessions with any page view, which needs page views to carry a
  session (FEAT-047's `analytics_visits` gives that).
- Patterns to lean on rather than invent: a horizontal stepped-bar funnel
  (bars proportional to the first step, drop-off percentage in the gap
  between bars), or a vertical one on narrow screens; Tailwind Plus
  application-ui data-display "stats" for the per-step number and delta
  glyph, and its progress-bar shapes for the proportional bars. Mixpanel
  and Amplitude funnels are the reference for what an operator expects to
  see: conversion from the first step and from the previous step, both.
- The previous range as a ghost bar behind each step's bar, or a small
  ▲/▼ with the percentage beside the count, replaces "new vs previous
  range". "new" (no previous data) needs its own quiet treatment.
- Component contract: a readonly list of `FunnelStep` values is what the
  Blade component receives today (`FunnelView`); the design should say
  what a step needs and nothing more, so `App\Analytics\Admin\Funnel`
  computes and `x-admin.analytics.funnel` draws. No JavaScript is
  required for a proportional bar; inline widths from the domain
  (`BarStrip` is the precedent) keep it server-rendered.
- The design skill's working files live under
  `__local__/design/`; the FEAT-045 canvas
  (https://claude.ai/code/artifact/4418bf2e-1563-4c8f-ba89-84c7eed0e126)
  and `__local__/design/admin-analytics/BUILD-BRIEF.md` carry the chrome
  class strings.
- FEAT-048's ninety days of activity is what makes the canvas honest;
  design against it.

## Related work

- FEAT-046 — the funnel query and the tile row this replaces
- FEAT-045 — the analytics drill-in and its design canvas
- FEAT-047 — visits and channels (the visitors unit)
- FEAT-048 — the activity seed the design is drawn against
- DSGN-005 — admin is small-screen first

## Working

2026-09-03, branch `php/funnel-design`.

Delivered:

- Design canvas: https://claude.ai/code/artifact/76b45703-2274-4eed-8d1e-5a21d9907799
  (source `__local__/design/funnel/`). Five artboards from the seeded store
  at 30 days converted to sessions per step, and the Nine Owls listing at
  90 days: desktop light, desktop dark, one listing, phone, and Option B.
- Pattern: Tailwind Plus Application UI › Data Display › Stats, the
  shared-borders grid the admin dashboard already uses, with the "with
  trending" delta glyph. Cell anatomy top to bottom: label and delta,
  count, a bar for this range's share of the first step, a thinner bar for
  the previous range's share, "x% of prerequisite · y% of visitors", side
  notes (favorited, cancelled). The lowest rate from its prerequisite
  carries the "largest drop" badge.
- Architecture note: `docs/funnel.md` (`ba041ed7`): the data flow, the
  step contract (`key`, `label`, `current`, `previous`, `change`, `rate`,
  `shareOfFirst`, `previousShareOfFirst`, `isLargestDrop`, `note`,
  `side`), the sessions-per-step unit, the drawing rules, and what an
  admin-defined funnel needs.

Accepted 2026-09-03: the primary artboards. Favorites leave the main
funnel; a favorites-to-purchase path can be a custom funnel later
(FEAT-049). Option B is withdrawn.

Implementation: FEAT-049 carries it — the query counts sessions per step
from a step list, and its detail page and the three existing mounts draw
the accepted design through one component.
