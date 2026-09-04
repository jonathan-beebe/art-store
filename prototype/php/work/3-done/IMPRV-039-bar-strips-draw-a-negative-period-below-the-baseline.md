---
id: IMPRV-039
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-039: Bar strips draw a negative period below the baseline

## Problem
`App\Domain\Analytics\BarStrip` scales non-negative counts; the earnings page's net-per-period chart can carry a negative period (a refund after payout) and uses a hand-rolled percentage idiom that tints a loss red with an sr-only label (FEAT-060). Two bar idioms exist for one shape.

## Goal
One bar strip draws every series the portal has, including one that dips below zero.

## Outcome
- `BarStrip` accepts negative values: a baseline sits where zero falls, positive bars rise from it and negative bars drop below it, each with its accessible name carrying the sign.
- The earnings page's net-per-period chart renders through `x-bar-strip`; the hand-rolled idiom is gone; loss periods keep their tint and label.
- Every other caller (dashboard strip, listing detail, admin analytics) is unchanged in appearance; a dataset covers all-positive, all-negative, and mixed series.
- `make precommit` green; `make check` green before the PR.

## Why it matters
A money chart that cannot show a loss misreads on the one page where the sign matters.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 15.

## Related work
- FEAT-060, FEAT-055, FEAT-044..048

## Working
`BarStrip::baseline(list<int> $values, list<string> $tips, int $maxPx): BarStripBaseline`
(new value object: `list<BarStripBar> $bars`, `int $baselinePx`). The tallest
positive value and the tallest negative magnitude each set the scale for
their own side; when nothing is negative the baseline collapses to the
strip's bottom edge — the identical picture `bars()`/`heights()` already
draw, byte for byte (proven by the "all positive" dataset row). `BarStripBar`
gains `$negative` (default false, so every existing construction is
unaffected). `bar-strip.blade.php` gains an optional `baseline` prop: a
positive bar rises from it, a `negative` one drops below it and tints red
(same mechanism `hot` already used), and a faint `<line>` marks the zero row;
omitted, rendering is byte-identical to before (`$zero = $baseline ?? $height`
reproduces the old `y = height - bar.height` exactly).

`EarningsPeriods::tallestNet()` replaced by `netStrip(int $maxPx):
BarStripBaseline`, built on `BarStrip::baseline()`; each tip's sign comes
free from `Money::format()`, with ", a net loss" appended the way the old
sr-only span did. `earnings.blade.php`'s hand-rolled per-period bars (own
height %, own red/indigo-shade logic, own sr-only span) are gone; the section
now renders one `<x-bar-strip>`. Dropped the old chart's lighter "current
period" shade — not in the ticket's outcome list, and carrying it through the
shared component would need a second per-bar flag for one caller's cosmetic
nuance.

Datasets: `BarStripTest.php` (domain) covers all-positive, all-negative, and
mixed signed series, plus tip pass-through and the empty/all-zero edge
(baseline still lands on the bottom edge, no division by zero).
`View/Components/BarStripTest.php` pins the blade geometry (baseline omitted
vs given, positive-above/negative-below, the zero line, the red tint).
`EarningsPeriodsTest.php` and `EarningsControllerTest.php` each cover a real
loss period (a refund landing in the period after its sale, backdating
`placed_at` so the sale doesn't also land there — a `deliveredFulfillmentFor`/
`paidFulfillmentFor` fixture only backdates the payment moment, not
`order.placed_at`, a foot-gun this ticket's build tripped on once).
Dashboard strip, listing detail, and admin analytics callers are untouched
(`BarStrip::bars()`/`heights()` unchanged) and their own existing tests
(`ListingControllerTest.php`, `EntityActivityTest.php`, `DashboardControllerTest.php`)
are the before/after pin — still green, unmodified.

Every touched sidecar scoped-run green: `BarStripTest.php` (10), the blade
component test (6), `EarningsPeriodsTest.php` (11), `EarningsControllerTest.php`
(7). `make precommit` green (pre-commit hook, this commit). Per the
coordinator's load-management note, `make check` is skipped here — the
orchestrator runs it once on the merged branch.

### Review fixes
- `baseline()` clamped the baseline itself but not each bar's own rounded
  height against the room left on its side of it: two extremes could each
  round up, overshooting `$maxPx` (the mixed dataset's `50/-30` rounded to
  63/38, one past the 100px box) or, on a lopsided swing (`1000/-1`),
  overshoot the baseline itself into a negative `y`. Each bar is now
  clamped to its own side's budget after the baseline is fixed; the mixed
  dataset reads `[63, 37, 13, 6, 2]` and a `[1000, -1]` case is added.
- `role="img"` (from `labelledby`) hides the SVG's per-bar `<title>`s from
  assistive technology; an `sr-only` `<ul>` beside the strip now carries
  every bar's own tooltip.
- The zero line read 1.6:1 against the panel; it now carries its own
  `text-gray-500 dark:text-gray-400` and `stroke-width="1"`.
- `BarStripBaseline` gained `heightPx` (the `$maxPx` passed in), so the
  earnings view reads the strip's height off the object instead of a
  second `160` literal; `netStrip()` is now called from `EarningsController`,
  not the view.
- Docblocks: `BarStrip::baseline()` said "the tallest positive value and
  the tallest negative magnitude each set the scale for their own side" —
  the code uses one shared scale for both; reworded. Contrast clauses
  dropped from `BarStrip.php` and `BarStripBaseline.php`. `$tips`'s
  description and `netStrip()`'s doc both said "accessible name"; `bars()`
  already calls the same kind of value a "tooltip" — matched the term.
  `EarningsPeriods::netStrip()` now hoists `$figures->net()` once instead
  of calling it three times per period.
- `docs/analytics.md` and `docs/seller-portal.md`'s Earnings section now
  name `baseline()`/`BarStripBaseline` and `netStrip()`/the loss tint.

Follow-ups filed rather than fixed here: IMPRV-041 (the admin listing tint
disagrees with `ListingStatus`'s own badge — the same rename this ticket's
sibling IMPRV-034 did, on a different enum) and IMPRV-042 (the net-per-period
bars and their date labels are two independent layouts that can drift out
of alignment).
