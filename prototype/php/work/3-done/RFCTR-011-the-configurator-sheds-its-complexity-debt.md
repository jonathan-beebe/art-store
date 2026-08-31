---
id: RFCTR-011
type: refactor
status: resolved
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

## Working

- `ConfiguratorPublishValidation::check()` (22) and
  `ConfigurationPricer::baseAndSurchargeLines()` (9) split into one private
  method per rule/branch, called in the same order; every existing sidecar
  test passed unmodified, so no characterization tests were needed for
  those two.
- `ConfiguratorPageResolver` (class 51) gave up three phases as their own
  collaborators, matching the `ListingPagePresenter` / `ListingHighlights`
  precedent: `SelectedAxisValues` (the axis-defaults-and-selection block),
  `ModifiersPresentation` (the modifier loop plus the four
  `resolve*Answer()` methods, `resolveMeasurementAnswer()` restructured
  from nested ternaries to a guard clause plus two one-line helpers to
  clear its own 9), and `SerializedUnitsPresentation` (the old
  `buildUnitsPresentation()`, 12). The combo-key map build, the
  quantity-tier build, and the configuration-snapshot build stayed as
  private methods on the resolver — small enough on their own once out of
  `resolve()`'s body. `hasConfigurator` and `buildAxesPresentation` were
  never flagged and are untouched.
- Confirmed with the vendored `tomasvotruba/cognitive-complexity` source
  that `||` and `match` never add to its score (only `&&`, ternaries, and
  the control-flow keywords do) and that "class" complexity is a flat sum
  over every method — both shaped which blocks were worth extracting.
- Each unit's existing sidecar suite passed unmodified both before and
  after its refactor, so no characterization tests were written for
  hidden behavior; the three new collaborators got their own direct
  sidecars instead (project convention: one `*Test.php` per class),
  exercising the shapes `resolve()` used to produce only as private-method
  side effects.
- `make analyse` confirmed all six baseline entries went stale
  (`ignore.unmatched`) before they were deleted, and clean after.
