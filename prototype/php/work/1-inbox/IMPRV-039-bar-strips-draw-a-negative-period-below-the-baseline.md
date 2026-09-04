---
id: IMPRV-039
type: improvement
status: open
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
