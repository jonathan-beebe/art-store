---
id: BUG-008
type: bug
status: open
created: 2026-08-27
---

# BUG-008: Removing an option a variant depends on has no path that succeeds

## Problem
On the Choices screen, removing an option value a variant selects refuses with "This option value is selected by a variant; remove that variant first." (`App\Domain\Configurator\ConfiguratorDeletionGuard::forOptionValue`, app/Domain/Configurator/ConfiguratorDeletionGuard.php:31). Removing the axis itself refuses with "This axis has a variant built from one of its values; remove or reassign that variant first." (`::forAxis`, same file, line 24). The reporter also hit "This option value is selected by a variant; remove that variant first." when attempting to remove what they describe as the blocking variant — the same option-value message, on an action they took to be removing the variant rather than the option.

## Goal
A seller can get from "an option or axis a variant depends on" to "removed," by some order of operations that actually succeeds.

## Outcome
The seller can remove an option value or axis a variant depends on, following a path the UI itself makes available, without landing back on the same refusal.

## Why it matters
As reported this reads as a dead end: the seller cannot remove the option, the axis, or the variant standing in the way, with the second error message not clearly describing the action that produced it.

## Discovery notes
Reported live against listing lst_01M128EBWPGB42G0ARSYMB999A. `routes/seller.php` registers `Route::resource('listings.variants', VariantController::class)->only(['index', 'store', 'update'])->scoped();` — there is no destroy route for a variant, and the Combinations & stock screen (resources/views/seller/listings/variants/index.blade.php) exposes no delete action for a variant row, only Save and the "Offered" checkbox. The reported "removing a child variant" attempt could not have reached a variant-delete action through the current UI; it is worth confirming exactly what control was used before deciding whether this is one bug (no way to remove the blocking variant at all) or two (that, plus a mismatched error message).

## Related work
- none
