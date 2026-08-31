---
id: RFCTR-011
type: refactor
status: open
created: 2026-08-31
---

# RFCTR-011: the configurator sheds its complexity debt

## Problem

The cognitive-complexity gate (function ≤ 8, class ≤ 50; commit 0c8700b)
holds six baseline entries in the configurator subsystem — the largest
cluster in the codebase and the only class-level breach:

- `ConfiguratorPageResolver` — class 51
  (`app/Support/Configurator/ConfiguratorPageResolver.php:35`), with
  `resolve()` at 17 (:51), `buildUnitsPresentation()` at 12 (:243), and
  `resolveMeasurementAnswer()` at 9 (:354)
- `ConfiguratorPublishValidation::check()` at 22
  (`app/Domain/Configurator/ConfiguratorPublishValidation.php:38`) — the
  single most complex function in the app
- `ConfigurationPricer::baseAndSurchargeLines()` at 9
  (`app/Support/Configurator/ConfigurationPricer.php:107`)

## Goal

The configurator reads at the same complexity ceiling as the rest of the
codebase, with no baseline entries left to carry it.

## Outcome

The six configurator entries are deleted from
`prototype/php/src/phpstan-baseline.neon` and `make analyse` passes; every
existing sidecar test passes unmodified; no new baseline entries appear.

## Why it matters

The configurator is the product's most intricate feature (axes, units,
modifiers, measurements resolved into one buyer page) and its complexity
scores say it is where the next defect hides and where the next feature
costs most. The gate keeps new code honest only if the standing debt
actually shrinks.

## Discovery notes

Advisory.

- `ConfiguratorPageResolver::resolve()` reads as sequential phases; the
  class-level 51 suggests the phases want to be their own collaborators
  rather than private methods, in the manner of the existing
  `ListingPagePresenter` / `ListingHighlights` split.
- `ConfiguratorPublishValidation::check()` at 22 looks like a rule list
  flattened into one method — a per-rule shape (small methods or rule
  objects yielding issues) tends to bring each unit under the ceiling
  without changing the issue vocabulary the seller editor renders.
- The tdd-refactor workflow fits: characterization tests first where the
  sidecars assert outcomes but not the intermediate shapes, then extract.
- Measure by running `make analyse` after deleting the six entries — the
  gate itself is the metric; no judgment call needed.

## Related work

- Commit 0c8700b — the gate and baseline this ticket shrinks
- Commits 573b98d / f0c6a0f — configurator feature and its query-side trim
