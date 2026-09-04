---
id: IMPRV-042
type: improvement
status: open
created: 2026-09-04
---

# IMPRV-042: The earnings net-per-period bars don't line up with their date labels

## Problem
`earnings.blade.php`'s net-per-period chart renders the eight bars through
`x-bar-strip`'s SVG (`viewBox`-scaled, `preserveAspectRatio="none"`) and the
eight period-start labels through a separate flex row underneath; the two
layouts scale independently, so a bar's horizontal center does not
necessarily sit under its own label.

## Goal
Each bar sits above the label naming its period.

## Outcome
The net-per-period chart's bars and date labels read as one column each, at
every width the section renders at.

## Why it matters
A chart that doesn't line up with its own axis reads wrong at a glance, on
the one page money numbers matter most.

## Discovery notes
- `resources/views/seller/earnings.blade.php`'s "Net per period" section:
  the `<x-bar-strip>` and the `<div class="flex gap-1 pt-1.5">` label row.
- `x-bar-strip`'s bars lay out inside one `viewBox`-scaled `<svg>`; the
  labels are a flex row of equal-width columns — two independent layout
  systems for eight positions each.

## Related work
- IMPRV-039 (introduced the chart this labels)
